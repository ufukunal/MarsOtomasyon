<?php

namespace App\Support\Reporting\Queries;

use App\Contracts\Reporting\ReportQuery;
use App\Models\SalesChannelAccount;
use App\Support\Period\PeriodContext;
use App\Support\Reporting\ReportColumnDefinition;
use App\Support\Reporting\ReportDefinition;
use App\Support\Reporting\ReportExecutionContext;
use App\Support\Reporting\ReportFilterDefinition;
use App\Support\Reporting\ReportQueryResult;
use App\Support\Reporting\ReportSort;
use App\Support\Reporting\ReportTotalDefinition;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class ChannelSyncStatusReportQuery implements ReportQuery
{
    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'channels.sync_status',
            title: 'Kanal Sync Durumu',
            category: 'E-ticaret',
            permission: 'channel_sync.view',
            filters: [
                new ReportFilterDefinition('date_from', 'Son deneme başlangıç', 'date'),
                new ReportFilterDefinition('date_to', 'Son deneme bitiş', 'date'),
                new ReportFilterDefinition('channel_account_id', 'Kanal Hesabı', 'positive_integer'),
                new ReportFilterDefinition('direction', 'Yön'),
                new ReportFilterDefinition('entity_type', 'Varlık Türü'),
                new ReportFilterDefinition('action', 'İşlem'),
                new ReportFilterDefinition('status', 'Durum'),
            ],
            columns: [
                new ReportColumnDefinition('id', 'ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('channel_account_id', 'Kanal Hesap ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('account_name', 'Kanal Hesabı', sortable: false),
                new ReportColumnDefinition('platform', 'Platform', sortable: false),
                new ReportColumnDefinition('direction', 'Yön'),
                new ReportColumnDefinition('entity_type', 'Varlık Türü'),
                new ReportColumnDefinition('action', 'İşlem'),
                new ReportColumnDefinition('status', 'Durum'),
                new ReportColumnDefinition('attempts', 'Deneme', 'integer'),
                new ReportColumnDefinition('last_attempt_at', 'Son Deneme', 'datetime'),
                new ReportColumnDefinition('error_summary', 'Hata Özeti', sortable: false),
            ],
            defaultSort: [
                new ReportSort('last_attempt_at', 'desc'),
                new ReportSort('id', 'desc'),
            ],
            totals: [
                new ReportTotalDefinition('events', 'Event', 'integer'),
                new ReportTotalDefinition('failed', 'Hatalı', 'integer'),
            ],
        );
    }

    public function execute(ReportExecutionContext $context): ReportQueryResult
    {
        $base = DB::connection('period')->table('channel_sync_events as e');
        $this->applyFilters($base, $context);
        $totalRows = (clone $base)->count('e.id');
        $rows = clone $base;

        if (! $context->hasColumn('id')) {
            $rows->selectRaw('e.id AS id');
        }
        if (! $context->hasColumn('channel_account_id')) {
            $rows->selectRaw('e.channel_account_id AS channel_account_id');
        }

        foreach ($context->columns as $column) {
            if (in_array($column, ['account_name', 'platform'], true)) {
                continue;
            }
            $rows->selectRaw($this->selectExpression($column));
        }

        $sortMap = $this->sortMap();
        $hasId = false;
        foreach ($context->sort as $sort) {
            $rows->orderBy($sortMap[$sort->key], $sort->direction);
            $hasId = $hasId || $sort->key === 'id';
        }
        if (! $hasId) {
            $rows->orderBy('e.id');
        }

        $data = $rows->offset($context->offset)->limit($context->limit)->get()
            ->map(fn (object $row): array => (array) $row)->all();
        $data = $this->enrichAccounts($data, $context);

        $totals = [];
        if ($context->totalKeys !== []) {
            $row = (clone $base)->selectRaw(
                "COUNT(*)::bigint AS events,
                 COUNT(*) FILTER (WHERE e.status = 'failed')::bigint AS failed"
            )->first();
            foreach ($context->totalKeys as $key) {
                $totals[$key] = (int) ($row->{$key} ?? 0);
            }
        }

        return new ReportQueryResult($data, $totals, $totalRows);
    }

    private function applyFilters(Builder $query, ReportExecutionContext $context): void
    {
        $filters = $context->filters;
        if (isset($filters['date_from'])) {
            $query->whereDate('e.last_attempt_at', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $query->whereDate('e.last_attempt_at', '<=', $filters['date_to']);
        }
        foreach (['channel_account_id', 'direction', 'entity_type', 'action', 'status'] as $key) {
            if (isset($filters[$key])) {
                $query->where('e.'.$key, $filters[$key]);
            }
        }
    }

    private function selectExpression(string $column): string
    {
        return [
            'id' => 'e.id AS id',
            'channel_account_id' => 'e.channel_account_id AS channel_account_id',
            'direction' => 'e.direction AS direction',
            'entity_type' => 'e.entity_type AS entity_type',
            'action' => 'e.action AS action',
            'status' => 'e.status AS status',
            'attempts' => 'e.attempts AS attempts',
            'last_attempt_at' => 'e.last_attempt_at::text AS last_attempt_at',
            'error_summary' => 'e.error_summary AS error_summary',
        ][$column];
    }

    /** @return array<string,string> */
    private function sortMap(): array
    {
        return [
            'id' => 'e.id',
            'channel_account_id' => 'e.channel_account_id',
            'direction' => 'e.direction',
            'entity_type' => 'e.entity_type',
            'action' => 'e.action',
            'status' => 'e.status',
            'attempts' => 'e.attempts',
            'last_attempt_at' => 'e.last_attempt_at',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function enrichAccounts(array $rows, ReportExecutionContext $context): array
    {
        if (! $context->hasColumn('account_name') && ! $context->hasColumn('platform')) {
            return $rows;
        }

        $ids = array_values(array_unique(array_map(
            fn (array $row): int => (int) $row['channel_account_id'],
            $rows,
        )));
        $accounts = SalesChannelAccount::query()
            ->where('company_id', PeriodContext::companyId())
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        foreach ($rows as &$row) {
            $account = $accounts->get((int) $row['channel_account_id']);
            if ($context->hasColumn('account_name')) {
                $row['account_name'] = $account?->name;
            }
            if ($context->hasColumn('platform')) {
                $row['platform'] = $account?->platform?->value;
            }
        }
        unset($row);

        return $rows;
    }
}
