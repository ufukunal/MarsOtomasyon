<?php

namespace App\Support\Reporting\Queries;

use App\Contracts\Reporting\ReportQuery;
use App\Enums\DocumentType;
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

final class SalesInvoiceReportQuery implements ReportQuery
{
    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'sales.invoices',
            title: 'Satış Dökümü',
            permission: 'sales_invoices.view',
            filters: [
                new ReportFilterDefinition('date_from', 'Başlangıç tarihi', 'date'),
                new ReportFilterDefinition('date_to', 'Bitiş tarihi', 'date'),
                new ReportFilterDefinition('contact_id', 'Cari', 'positive_integer'),
                new ReportFilterDefinition('status', 'Durum'),
                new ReportFilterDefinition('currency', 'Döviz'),
            ],
            columns: [
                new ReportColumnDefinition('id', 'ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('number', 'Belge No'),
                new ReportColumnDefinition('document_date', 'Belge Tarihi', 'date'),
                new ReportColumnDefinition('contact_code', 'Cari Kod'),
                new ReportColumnDefinition('contact_title', 'Cari Ünvan'),
                new ReportColumnDefinition('currency', 'Döviz'),
                new ReportColumnDefinition('status', 'Durum'),
                new ReportColumnDefinition('subtotal', 'Ara Toplam', 'decimal'),
                new ReportColumnDefinition('discount_amount', 'İskonto', 'decimal'),
                new ReportColumnDefinition('vat_amount', 'KDV', 'decimal'),
                new ReportColumnDefinition('grand_total', 'Genel Toplam', 'decimal'),
            ],
            defaultSort: [
                new ReportSort('document_date', 'desc'),
                new ReportSort('id', 'desc'),
            ],
            totals: [
                new ReportTotalDefinition('subtotal', 'Ara Toplam'),
                new ReportTotalDefinition('discount_amount', 'İskonto'),
                new ReportTotalDefinition('vat_amount', 'KDV'),
                new ReportTotalDefinition('grand_total', 'Genel Toplam'),
            ],
            drillDowns: [
                new ReportDrillDownDefinition(
                    key: 'sales_invoice',
                    label: 'Satış Faturası',
                    permission: 'sales_invoices.view',
                    idColumn: 'id',
                    target: 'sales_invoices',
                ),
            ],
        );
    }

    public function execute(ReportExecutionContext $context): ReportQueryResult
    {
        $base = DB::connection('period')
            ->table('documents as d')
            ->leftJoin('contacts as c', 'c.id', '=', 'd.contact_id')
            ->where('d.document_type', DocumentType::SalesInvoice->value);

        $this->applyFilters($base, $context);

        $totalRows = (clone $base)->count('d.id');
        $rows = clone $base;

        if (! $context->hasColumn('id')) {
            $rows->selectRaw('d.id AS id');
        }

        foreach ($context->columns as $column) {
            $rows->selectRaw($this->selectExpression($column));
        }

        $sortMap = $this->sortMap();
        $hasIdSort = false;

        foreach ($context->sort as $sort) {
            $rows->orderBy($sortMap[$sort->key], $sort->direction);
            $hasIdSort = $hasIdSort || $sort->key === 'id';
        }

        if (! $hasIdSort) {
            $rows->orderBy('d.id', 'asc');
        }

        $data = $rows
            ->offset($context->offset)
            ->limit($context->limit)
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();

        return new ReportQueryResult(
            rows: $data,
            totals: $this->totals($base, $context),
            totalRows: $totalRows,
        );
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

        if (isset($filters['contact_id'])) {
            $query->where('d.contact_id', $filters['contact_id']);
        }

        if (isset($filters['status'])) {
            $query->where('d.status', $filters['status']);
        }

        if (isset($filters['currency'])) {
            $query->where('d.currency', strtoupper((string) $filters['currency']));
        }
    }

    private function selectExpression(string $column): string
    {
        return [
            'id' => 'd.id AS id',
            'number' => 'd.number AS number',
            'document_date' => 'd.document_date::text AS document_date',
            'contact_code' => 'c.code AS contact_code',
            'contact_title' => 'c.title AS contact_title',
            'currency' => 'd.currency AS currency',
            'status' => 'd.status AS status',
            'subtotal' => 'd.subtotal::text AS subtotal',
            'discount_amount' => 'd.discount_amount::text AS discount_amount',
            'vat_amount' => 'd.vat_amount::text AS vat_amount',
            'grand_total' => 'd.grand_total::text AS grand_total',
        ][$column];
    }

    /** @return array<string,string> */
    private function sortMap(): array
    {
        return [
            'id' => 'd.id',
            'number' => 'd.number',
            'document_date' => 'd.document_date',
            'contact_code' => 'c.code',
            'contact_title' => 'c.title',
            'currency' => 'd.currency',
            'status' => 'd.status',
            'subtotal' => 'd.subtotal',
            'discount_amount' => 'd.discount_amount',
            'vat_amount' => 'd.vat_amount',
            'grand_total' => 'd.grand_total',
        ];
    }

    /** @return array<string,mixed> */
    private function totals(Builder $base, ReportExecutionContext $context): array
    {
        $expressions = [
            'subtotal' => 'COALESCE(SUM(d.subtotal), 0)::text AS subtotal',
            'discount_amount' => 'COALESCE(SUM(d.discount_amount), 0)::text AS discount_amount',
            'vat_amount' => 'COALESCE(SUM(d.vat_amount), 0)::text AS vat_amount',
            'grand_total' => 'COALESCE(SUM(d.grand_total), 0)::text AS grand_total',
        ];
        $query = clone $base;
        $selected = [];

        foreach ($context->totalKeys as $key) {
            if (isset($expressions[$key])) {
                $query->selectRaw($expressions[$key]);
                $selected[] = $key;
            }
        }

        if ($selected === []) {
            return [];
        }

        $row = $query->first();

        return collect($selected)
            ->mapWithKeys(fn (string $key): array => [$key => $row->{$key} ?? '0'])
            ->all();
    }
}
