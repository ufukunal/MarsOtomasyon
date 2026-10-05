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

final class ProductionOrderReportQuery implements ReportQuery
{
    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'production.orders',
            title: 'Üretim / Fason Emirleri',
            category: 'Üretim / Fason',
            permission: 'production_orders.view',
            filters: [
                new ReportFilterDefinition('date_from', 'Başlangıç tarihi', 'date'),
                new ReportFilterDefinition('date_to', 'Bitiş tarihi', 'date'),
                new ReportFilterDefinition('product_id', 'Ürün', 'positive_integer'),
                new ReportFilterDefinition('subcontractor_contact_id', 'Fason Cari', 'positive_integer'),
                new ReportFilterDefinition('production_type', 'Üretim Türü'),
                new ReportFilterDefinition('status', 'Durum'),
            ],
            columns: [
                new ReportColumnDefinition('id', 'ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('number', 'Emir No'),
                new ReportColumnDefinition('document_date', 'İş Tarihi', 'date'),
                new ReportColumnDefinition('product_code', 'Ürün Kod'),
                new ReportColumnDefinition('product_name', 'Ürün'),
                new ReportColumnDefinition('production_type', 'Üretim Türü'),
                new ReportColumnDefinition('subcontractor_title', 'Fason Cari'),
                new ReportColumnDefinition('planned_quantity', 'Planlanan', 'decimal'),
                new ReportColumnDefinition('completed_quantity', 'Tamamlanan', 'decimal'),
                new ReportColumnDefinition('cancelled_quantity', 'İptal', 'decimal'),
                new ReportColumnDefinition('remaining_quantity', 'Kalan', 'decimal'),
                new ReportColumnDefinition('status', 'Durum'),
            ],
            defaultSort: [
                new ReportSort('document_date', 'desc'),
                new ReportSort('id', 'desc'),
            ],
            totals: [
                new ReportTotalDefinition('planned_quantity', 'Planlanan'),
                new ReportTotalDefinition('completed_quantity', 'Tamamlanan'),
                new ReportTotalDefinition('cancelled_quantity', 'İptal'),
                new ReportTotalDefinition('remaining_quantity', 'Kalan'),
            ],
            drillDowns: [
                new ReportDrillDownDefinition(
                    'production_order',
                    'Üretim Emri',
                    'production_orders.view',
                    'id',
                    'production_orders',
                ),
            ],
        );
    }

    public function execute(ReportExecutionContext $context): ReportQueryResult
    {
        $base = DB::connection('period')
            ->table('production_orders as o')
            ->join('products as p', 'p.id', '=', 'o.product_id')
            ->leftJoin('contacts as c', 'c.id', '=', 'o.subcontractor_contact_id');

        $this->applyFilters($base, $context);
        $totalRows = (clone $base)->count('o.id');
        $rows = clone $base;

        if (! $context->hasColumn('id')) {
            $rows->selectRaw('o.id AS id');
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
            $rows->orderBy('o.id');
        }

        $totals = [];
        if ($context->totalKeys !== []) {
            $row = (clone $base)->selectRaw(
                'COALESCE(SUM(o.planned_quantity),0)::text AS planned_quantity,
                 COALESCE(SUM(o.completed_quantity),0)::text AS completed_quantity,
                 COALESCE(SUM(o.cancelled_quantity),0)::text AS cancelled_quantity,
                 COALESCE(SUM(o.planned_quantity-o.completed_quantity-o.cancelled_quantity),0)::text AS remaining_quantity'
            )->first();

            foreach ($context->totalKeys as $key) {
                $totals[$key] = $row->{$key} ?? '0';
            }
        }

        return new ReportQueryResult(
            rows: $rows->offset($context->offset)->limit($context->limit)->get()
                ->map(fn (object $row): array => (array) $row)->all(),
            totals: $totals,
            totalRows: $totalRows,
        );
    }

    private function applyFilters(Builder $query, ReportExecutionContext $context): void
    {
        $filters = $context->filters;

        if (isset($filters['date_from'])) {
            $query->whereDate('o.document_date', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $query->whereDate('o.document_date', '<=', $filters['date_to']);
        }

        foreach (['product_id', 'subcontractor_contact_id', 'production_type', 'status'] as $key) {
            if (isset($filters[$key])) {
                $query->where('o.'.$key, $filters[$key]);
            }
        }
    }

    private function selectExpression(string $column): string
    {
        return [
            'id' => 'o.id AS id',
            'number' => 'o.number AS number',
            'document_date' => 'o.document_date::text AS document_date',
            'product_code' => 'p.code AS product_code',
            'product_name' => 'p.name AS product_name',
            'production_type' => 'o.production_type AS production_type',
            'subcontractor_title' => 'c.title AS subcontractor_title',
            'planned_quantity' => 'o.planned_quantity::text AS planned_quantity',
            'completed_quantity' => 'o.completed_quantity::text AS completed_quantity',
            'cancelled_quantity' => 'o.cancelled_quantity::text AS cancelled_quantity',
            'remaining_quantity' => '(o.planned_quantity-o.completed_quantity-o.cancelled_quantity)::text AS remaining_quantity',
            'status' => 'o.status AS status',
        ][$column];
    }

    private function sortMap(): array
    {
        return [
            'id' => 'o.id',
            'number' => 'o.number',
            'document_date' => 'o.document_date',
            'product_code' => 'p.code',
            'product_name' => 'p.name',
            'production_type' => 'o.production_type',
            'subcontractor_title' => 'c.title',
            'planned_quantity' => 'o.planned_quantity',
            'completed_quantity' => 'o.completed_quantity',
            'cancelled_quantity' => 'o.cancelled_quantity',
            'remaining_quantity' => DB::raw('(o.planned_quantity-o.completed_quantity-o.cancelled_quantity)'),
            'status' => 'o.status',
        ];
    }
}
