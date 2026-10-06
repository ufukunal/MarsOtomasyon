<?php

namespace App\Support\Reporting\Queries;

use App\Contracts\Reporting\ReportQuery;
use App\Enums\DocumentType;
use App\Models\SalesChannelAccount;
use App\Support\Period\PeriodContext;
use App\Support\Reporting\ReportColumnDefinition;
use App\Support\Reporting\ReportDefinition;
use App\Support\Reporting\ReportDrillDownDefinition;
use App\Support\Reporting\ReportExecutionContext;
use App\Support\Reporting\ReportFilterDefinition;
use App\Support\Reporting\ReportQueryResult;
use App\Support\Reporting\ReportSort;
use App\Support\Reporting\ReportTotalDefinition;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class ChannelOrderReportQuery implements ReportQuery
{
    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'channels.orders',
            title: 'Kanal Siparişleri',
            category: 'E-ticaret',
            permission: 'channel_sync.view',
            filters: [
                new ReportFilterDefinition('date_from', 'Başlangıç tarihi', 'date'),
                new ReportFilterDefinition('date_to', 'Bitiş tarihi', 'date'),
                new ReportFilterDefinition('channel_account_id', 'Kanal Hesabı', 'positive_integer'),
                new ReportFilterDefinition('status', 'Sipariş Durumu'),
            ],
            columns: [
                new ReportColumnDefinition('snapshot_id', 'Snapshot ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('sales_order_id', 'Sipariş ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('channel_account_id', 'Kanal Hesap ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('account_name', 'Kanal Hesabı', sortable: false),
                new ReportColumnDefinition('platform', 'Platform', sortable: false),
                new ReportColumnDefinition('external_order_no', 'Kanal Sipariş No'),
                new ReportColumnDefinition('number', 'ERP Sipariş No'),
                new ReportColumnDefinition('document_date', 'Sipariş Tarihi', 'date'),
                new ReportColumnDefinition('status', 'Durum'),
                new ReportColumnDefinition('currency', 'Döviz'),
                new ReportColumnDefinition('grand_total', 'Tutar', 'decimal'),
                new ReportColumnDefinition('external_package_id', 'Paket ID'),
            ],
            defaultSort: [
                new ReportSort('document_date', 'desc'),
                new ReportSort('snapshot_id', 'desc'),
            ],
            totals: [
                new ReportTotalDefinition('orders', 'Sipariş', 'integer'),
                new ReportTotalDefinition('grand_total', 'Toplam Tutar'),
            ],
            drillDowns: [
                new ReportDrillDownDefinition(
                    'sales_order',
                    'Satış Siparişi',
                    'sales_orders.view',
                    'sales_order_id',
                    'sales_orders',
                ),
            ],
        );
    }

    public function execute(ReportExecutionContext $context): ReportQueryResult
    {
        $base = DB::connection('period')
            ->table('channel_order_snapshots as s')
            ->join('documents as d', 'd.id', '=', 's.sales_order_id')
            ->where('d.document_type', DocumentType::SalesOrder->value);

        $this->applyFilters($base, $context);
        $totalRows = (clone $base)->count('s.id');
        $rows = clone $base;

        foreach ([
            'snapshot_id' => 's.id AS snapshot_id',
            'sales_order_id' => 's.sales_order_id AS sales_order_id',
            'channel_account_id' => 's.channel_account_id AS channel_account_id',
        ] as $key => $select) {
            if (! $context->hasColumn($key)) {
                $rows->selectRaw($select);
            }
        }

        foreach ($context->columns as $column) {
            if (in_array($column, ['account_name', 'platform'], true)) {
                continue;
            }
            $rows->selectRaw($this->selectExpression($column));
        }

        $sortMap = $this->sortMap();
        $hasSnapshotId = false;
        foreach ($context->sort as $sort) {
            $rows->orderBy($sortMap[$sort->key], $sort->direction);
            $hasSnapshotId = $hasSnapshotId || $sort->key === 'snapshot_id';
        }
        if (! $hasSnapshotId) {
            $rows->orderBy('s.id');
        }

        $data = $rows->offset($context->offset)->limit($context->limit)->get()
            ->map(fn (object $row): array => (array) $row)->all();
        $data = $this->enrichAccounts($data, $context);

        $totals = [];
        if ($context->totalKeys !== []) {
            $row = (clone $base)->selectRaw(
                'COUNT(*)::bigint AS orders, COALESCE(SUM(d.grand_total),0)::text AS grand_total'
            )->first();
            foreach ($context->totalKeys as $key) {
                $totals[$key] = $key === 'orders'
                    ? (int) ($row->orders ?? 0)
                    : ($row->{$key} ?? '0');
            }
        }

        return new ReportQueryResult($data, $totals, $totalRows);
    }

    private function applyFilters(Builder $query, ReportExecutionContext $context): void
    {
        $filters = $context->filters;
        if (isset($filters['date_from'])) {
            $query->whereDate('d.document_date', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $query->whereDate('d.document_date', '<=', $filters['date_to']);
        }
        if (isset($filters['channel_account_id'])) {
            $query->where('s.channel_account_id', $filters['channel_account_id']);
        }
        if (isset($filters['status'])) {
            $query->where('d.status', $filters['status']);
        }
    }

    private function selectExpression(string $column): string
    {
        return [
            'snapshot_id' => 's.id AS snapshot_id',
            'sales_order_id' => 's.sales_order_id AS sales_order_id',
            'channel_account_id' => 's.channel_account_id AS channel_account_id',
            'external_order_no' => 's.external_order_no AS external_order_no',
            'number' => 'd.number AS number',
            'document_date' => 'd.document_date::text AS document_date',
            'status' => 'd.status AS status',
            'currency' => 'd.currency AS currency',
            'grand_total' => 'd.grand_total::text AS grand_total',
            'external_package_id' => 's.external_package_id AS external_package_id',
        ][$column];
    }

    /** @return array<string,string> */
    private function sortMap(): array
    {
        return [
            'snapshot_id' => 's.id',
            'sales_order_id' => 's.sales_order_id',
            'channel_account_id' => 's.channel_account_id',
            'external_order_no' => 's.external_order_no',
            'number' => 'd.number',
            'document_date' => 'd.document_date',
            'status' => 'd.status',
            'currency' => 'd.currency',
            'grand_total' => 'd.grand_total',
            'external_package_id' => 's.external_package_id',
        ];
    }

    /**
     * @param  list<array<string,mixed>>  $rows
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
