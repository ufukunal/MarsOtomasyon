<?php

namespace App\Support\Reporting\Queries;

use App\Contracts\Reporting\ReportQuery;
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

final class ContactTransactionReportQuery implements ReportQuery
{
    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'contacts.transactions',
            title: 'Cari Hareket Dökümü',
            category: 'Cari',
            permission: 'contacts.view',
            filters: [
                new ReportFilterDefinition('date_from', 'Başlangıç tarihi', 'date'),
                new ReportFilterDefinition('date_to', 'Bitiş tarihi', 'date'),
                new ReportFilterDefinition('contact_id', 'Cari', 'positive_integer'),
                new ReportFilterDefinition('direction', 'Borç/Alacak'),
                new ReportFilterDefinition('transaction_type', 'Hareket Türü'),
                new ReportFilterDefinition('currency', 'Döviz'),
            ],
            columns: [
                new ReportColumnDefinition('id', 'ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('contact_id', 'Cari ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('transaction_date', 'İşlem Tarihi', 'date'),
                new ReportColumnDefinition('contact_code', 'Cari Kod'),
                new ReportColumnDefinition('contact_title', 'Cari Ünvan'),
                new ReportColumnDefinition('transaction_type', 'Hareket Türü'),
                new ReportColumnDefinition('direction', 'Yön'),
                new ReportColumnDefinition('amount', 'Tutar', 'decimal'),
                new ReportColumnDefinition('signed_amount', 'Bakiye Etkisi', 'decimal'),
                new ReportColumnDefinition('currency', 'Döviz'),
                new ReportColumnDefinition('due_date', 'Vade', 'date'),
                new ReportColumnDefinition('description', 'Açıklama', sortable: false),
            ],
            defaultSort: [
                new ReportSort('transaction_date', 'desc'),
                new ReportSort('id', 'desc'),
            ],
            totals: [
                new ReportTotalDefinition('debit', 'Borç'),
                new ReportTotalDefinition('credit', 'Alacak'),
                new ReportTotalDefinition('balance', 'Bakiye'),
            ],
            drillDowns: [
                new ReportDrillDownDefinition(
                    key: 'contact',
                    label: 'Cari',
                    permission: 'contacts.view',
                    idColumn: 'contact_id',
                    target: 'contacts',
                ),
            ],
        );
    }

    public function execute(ReportExecutionContext $context): ReportQueryResult
    {
        $base = DB::connection('period')
            ->table('contact_transactions as ct')
            ->join('contacts as c', 'c.id', '=', 'ct.contact_id');

        $this->applyFilters($base, $context);
        $totalRows = (clone $base)->count('ct.id');
        $rows = clone $base;

        if (! $context->hasColumn('id')) {
            $rows->selectRaw('ct.id AS id');
        }

        if (! $context->hasColumn('contact_id')) {
            $rows->selectRaw('ct.contact_id AS contact_id');
        }

        foreach ($context->columns as $column) {
            $rows->selectRaw($this->selectExpression($column));
        }

        $sortMap = $this->sortMap();
        $hasId = false;

        foreach ($context->sort as $sort) {
            $rows->orderBy($sortMap[$sort->key], $sort->direction);
            $hasId = $hasId || $sort->key === 'id';
        }

        if (! $hasId) {
            $rows->orderBy('ct.id');
        }

        return new ReportQueryResult(
            rows: $rows->offset($context->offset)->limit($context->limit)->get()
                ->map(fn (object $row): array => (array) $row)->all(),
            totals: $this->totals($base, $context),
            totalRows: $totalRows,
        );
    }

    private function applyFilters(Builder $query, ReportExecutionContext $context): void
    {
        $filters = $context->filters;

        if (isset($filters['date_from'])) {
            $query->whereDate('ct.transaction_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('ct.transaction_date', '<=', $filters['date_to']);
        }

        foreach (['contact_id', 'direction', 'transaction_type'] as $key) {
            if (isset($filters[$key])) {
                $query->where('ct.'.$key, $filters[$key]);
            }
        }

        if (isset($filters['currency'])) {
            $query->where('ct.currency', strtoupper((string) $filters['currency']));
        }
    }

    private function selectExpression(string $column): string
    {
        return [
            'id' => 'ct.id AS id',
            'contact_id' => 'ct.contact_id AS contact_id',
            'transaction_date' => 'ct.transaction_date::text AS transaction_date',
            'contact_code' => 'c.code AS contact_code',
            'contact_title' => 'c.title AS contact_title',
            'transaction_type' => 'ct.transaction_type AS transaction_type',
            'direction' => 'ct.direction AS direction',
            'amount' => 'ct.amount::text AS amount',
            'signed_amount' => "(CASE WHEN ct.direction = 'debit' THEN ct.amount ELSE -ct.amount END)::text AS signed_amount",
            'currency' => 'ct.currency AS currency',
            'due_date' => 'ct.due_date::text AS due_date',
            'description' => 'ct.description AS description',
        ][$column];
    }

    /** @return array<string,mixed> */
    private function sortMap(): array
    {
        return [
            'id' => 'ct.id',
            'contact_id' => 'ct.contact_id',
            'transaction_date' => 'ct.transaction_date',
            'contact_code' => 'c.code',
            'contact_title' => 'c.title',
            'transaction_type' => 'ct.transaction_type',
            'direction' => 'ct.direction',
            'amount' => 'ct.amount',
            'signed_amount' => DB::raw("(CASE WHEN ct.direction = 'debit' THEN ct.amount ELSE -ct.amount END)"),
            'currency' => 'ct.currency',
            'due_date' => 'ct.due_date',
        ];
    }

    /** @return array<string,mixed> */
    private function totals(Builder $base, ReportExecutionContext $context): array
    {
        if ($context->totalKeys === []) {
            return [];
        }

        $row = (clone $base)->selectRaw(
            "COALESCE(SUM(CASE WHEN ct.direction = 'debit' THEN ct.amount ELSE 0 END), 0)::text AS debit,
             COALESCE(SUM(CASE WHEN ct.direction = 'credit' THEN ct.amount ELSE 0 END), 0)::text AS credit,
             COALESCE(SUM(CASE WHEN ct.direction = 'debit' THEN ct.amount ELSE -ct.amount END), 0)::text AS balance"
        )->first();

        $values = [
            'debit' => $row->debit ?? '0',
            'credit' => $row->credit ?? '0',
            'balance' => $row->balance ?? '0',
        ];

        return array_intersect_key($values, array_fill_keys($context->totalKeys, true));
    }
}
