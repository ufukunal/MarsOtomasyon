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
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class ImportFileReportQuery implements ReportQuery
{
    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'imports.files',
            title: 'İthalat Dosyaları',
            category: 'İthalat',
            permission: 'import_shipments.view',
            filters: [
                new ReportFilterDefinition('date_from', 'ETA başlangıç', 'date'),
                new ReportFilterDefinition('date_to', 'ETA bitiş', 'date'),
                new ReportFilterDefinition('supplier_contact_id', 'Tedarikçi', 'positive_integer'),
                new ReportFilterDefinition('status', 'Durum'),
                new ReportFilterDefinition('currency', 'Döviz'),
            ],
            columns: [
                new ReportColumnDefinition('id', 'ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('number', 'Dosya No'),
                new ReportColumnDefinition('supplier_code', 'Tedarikçi Kod'),
                new ReportColumnDefinition('supplier_title', 'Tedarikçi'),
                new ReportColumnDefinition('receiving_location', 'Teslim Lokasyonu'),
                new ReportColumnDefinition('country', 'Ülke'),
                new ReportColumnDefinition('incoterm', 'Incoterm'),
                new ReportColumnDefinition('currency', 'Döviz'),
                new ReportColumnDefinition('exchange_rate', 'Kur', 'decimal'),
                new ReportColumnDefinition('etd', 'ETD', 'date'),
                new ReportColumnDefinition('eta', 'ETA', 'date'),
                new ReportColumnDefinition('received_at', 'Teslim Tarihi', 'date'),
                new ReportColumnDefinition('status', 'Durum'),
            ],
            defaultSort: [
                new ReportSort('eta'),
                new ReportSort('id'),
            ],
            drillDowns: [
                new ReportDrillDownDefinition(
                    'import_file',
                    'İthalat Dosyası',
                    'import_shipments.view',
                    'id',
                    'import_shipments',
                ),
            ],
        );
    }

    public function execute(ReportExecutionContext $context): ReportQueryResult
    {
        $base = DB::connection('period')
            ->table('import_files as f')
            ->leftJoin('contacts as c', 'c.id', '=', 'f.supplier_contact_id')
            ->leftJoin('locations as l', 'l.id', '=', 'f.receiving_location_id');

        $this->applyFilters($base, $context);
        $totalRows = (clone $base)->count('f.id');
        $rows = clone $base;

        if (! $context->hasColumn('id')) {
            $rows->selectRaw('f.id AS id');
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
            $rows->orderBy('f.id');
        }

        return new ReportQueryResult(
            rows: $rows->offset($context->offset)->limit($context->limit)->get()
                ->map(fn (object $row): array => (array) $row)->all(),
            totals: [],
            totalRows: $totalRows,
        );
    }

    private function applyFilters(Builder $query, ReportExecutionContext $context): void
    {
        $filters = $context->filters;

        if (isset($filters['date_from'])) {
            $query->whereDate('f.eta', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('f.eta', '<=', $filters['date_to']);
        }

        if (isset($filters['supplier_contact_id'])) {
            $query->where('f.supplier_contact_id', $filters['supplier_contact_id']);
        }

        if (isset($filters['status'])) {
            $query->where('f.status', $filters['status']);
        }

        if (isset($filters['currency'])) {
            $query->where('f.currency', strtoupper((string) $filters['currency']));
        }
    }

    private function selectExpression(string $column): string
    {
        return [
            'id' => 'f.id AS id',
            'number' => 'f.number AS number',
            'supplier_code' => 'c.code AS supplier_code',
            'supplier_title' => 'c.title AS supplier_title',
            'receiving_location' => 'l.name AS receiving_location',
            'country' => 'f.country AS country',
            'incoterm' => 'f.incoterm AS incoterm',
            'currency' => 'f.currency AS currency',
            'exchange_rate' => 'f.exchange_rate::text AS exchange_rate',
            'etd' => 'f.etd::text AS etd',
            'eta' => 'f.eta::text AS eta',
            'received_at' => 'f.received_at::text AS received_at',
            'status' => 'f.status AS status',
        ][$column];
    }

    /** @return array<string,string> */
    private function sortMap(): array
    {
        return [
            'id' => 'f.id',
            'number' => 'f.number',
            'supplier_code' => 'c.code',
            'supplier_title' => 'c.title',
            'receiving_location' => 'l.name',
            'country' => 'f.country',
            'incoterm' => 'f.incoterm',
            'currency' => 'f.currency',
            'exchange_rate' => 'f.exchange_rate',
            'etd' => 'f.etd',
            'eta' => 'f.eta',
            'received_at' => 'f.received_at',
            'status' => 'f.status',
        ];
    }
}
