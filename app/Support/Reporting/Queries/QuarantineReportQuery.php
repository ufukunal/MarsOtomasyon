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
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

final class QuarantineReportQuery implements ReportQuery
{
    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'returns.quarantine',
            title: 'Karantina Durumu',
            category: 'İade / Karantina',
            permission: 'quarantine.view',
            filters: [
                new ReportFilterDefinition('product_id', 'Ürün', 'positive_integer'),
                new ReportFilterDefinition('location_id', 'Lokasyon', 'positive_integer'),
                new ReportFilterDefinition('status', 'Durum'),
            ],
            columns: [
                new ReportColumnDefinition('id', 'ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('product_id', 'Ürün ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('product_code', 'Ürün Kod'),
                new ReportColumnDefinition('product_name', 'Ürün'),
                new ReportColumnDefinition('location_code', 'Lokasyon Kod'),
                new ReportColumnDefinition('location_name', 'Lokasyon'),
                new ReportColumnDefinition('status', 'Durum'),
                new ReportColumnDefinition('quantity', 'Miktar', 'decimal'),
                new ReportColumnDefinition('released_quantity', 'Serbest', 'decimal'),
                new ReportColumnDefinition('scrapped_quantity', 'Hurda', 'decimal'),
                new ReportColumnDefinition('reversed_quantity', 'Ters Kayıt', 'decimal'),
                new ReportColumnDefinition('pending_quantity', 'Bekleyen', 'decimal'),
                new ReportColumnDefinition('unit_cost', 'Birim Maliyet', 'decimal', costSensitive: true),
                new ReportColumnDefinition('pending_value', 'Bekleyen Değer', 'decimal', costSensitive: true),
            ],
            defaultSort: [new ReportSort('product_code'), new ReportSort('id')],
            totals: [
                new ReportTotalDefinition('quantity', 'Miktar'),
                new ReportTotalDefinition('pending_quantity', 'Bekleyen'),
                new ReportTotalDefinition('pending_value', 'Bekleyen Değer', costSensitive: true),
            ],
            drillDowns: [
                new ReportDrillDownDefinition('product', 'Ürün', 'products.view', 'product_id', 'products'),
            ],
        );
    }

    public function execute(ReportExecutionContext $context): ReportQueryResult
    {
        $base = DB::connection('period')->table('quarantine_entries as q')
            ->join('products as p', 'p.id', '=', 'q.product_id')
            ->join('locations as l', 'l.id', '=', 'q.location_id');
        $f = $context->filters;
        if (isset($f['product_id'])) {
            $base->where('q.product_id', $f['product_id']);
        }
        if (isset($f['location_id'])) {
            $base->where('q.location_id', $f['location_id']);
        }
        if (isset($f['status'])) {
            $base->where('q.status', $f['status']);
        }

        $totalRows = (clone $base)->count('q.id');
        $rows = clone $base;
        if (! $context->hasColumn('id')) {
            $rows->selectRaw('q.id AS id');
        }
        if (! $context->hasColumn('product_id')) {
            $rows->selectRaw('q.product_id AS product_id');
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
            $rows->orderBy('q.id');
        }

        $totals = [];
        if ($context->totalKeys !== []) {
            $row = (clone $base)->selectRaw(
                'COALESCE(SUM(q.quantity),0)::text AS quantity,
                 COALESCE(SUM(q.quantity-q.released_quantity-q.scrapped_quantity-q.reversed_quantity),0)::text AS pending_quantity'.
                 ($context->hasTotal('pending_value')
                    ? ', COALESCE(SUM(trunc((q.quantity-q.released_quantity-q.scrapped_quantity-q.reversed_quantity)*q.unit_cost,4)),0)::text AS pending_value'
                    : '')
            )->first();
            foreach ($context->totalKeys as $key) {
                $totals[$key] = $row->{$key} ?? '0';
            }
        }

        return new ReportQueryResult(
            rows: $rows->offset($context->offset)->limit($context->limit)->get()->map(fn (object $r): array => (array) $r)->all(),
            totals: $totals, totalRows: $totalRows,
        );
    }

    private function selectExpression(string $column): string
    {
        return [
            'id' => 'q.id AS id', 'product_id' => 'q.product_id AS product_id',
            'product_code' => 'p.code AS product_code', 'product_name' => 'p.name AS product_name',
            'location_code' => 'l.code AS location_code', 'location_name' => 'l.name AS location_name',
            'status' => 'q.status AS status', 'quantity' => 'q.quantity::text AS quantity',
            'released_quantity' => 'q.released_quantity::text AS released_quantity',
            'scrapped_quantity' => 'q.scrapped_quantity::text AS scrapped_quantity',
            'reversed_quantity' => 'q.reversed_quantity::text AS reversed_quantity',
            'pending_quantity' => '(q.quantity-q.released_quantity-q.scrapped_quantity-q.reversed_quantity)::text AS pending_quantity',
            'unit_cost' => 'q.unit_cost::text AS unit_cost',
            'pending_value' => 'trunc((q.quantity-q.released_quantity-q.scrapped_quantity-q.reversed_quantity)*q.unit_cost,4)::text AS pending_value',
        ][$column];
    }

    /** @return array<string,Expression|string> */
    private function sortMap(): array
    {
        return [
            'id' => 'q.id', 'product_id' => 'q.product_id', 'product_code' => 'p.code', 'product_name' => 'p.name',
            'location_code' => 'l.code', 'location_name' => 'l.name', 'status' => 'q.status', 'quantity' => 'q.quantity',
            'released_quantity' => 'q.released_quantity', 'scrapped_quantity' => 'q.scrapped_quantity',
            'reversed_quantity' => 'q.reversed_quantity',
            'pending_quantity' => DB::raw('(q.quantity-q.released_quantity-q.scrapped_quantity-q.reversed_quantity)'),
            'unit_cost' => 'q.unit_cost', 'pending_value' => DB::raw('((q.quantity-q.released_quantity-q.scrapped_quantity-q.reversed_quantity)*q.unit_cost)'),
        ];
    }
}
