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

final class StockBalanceReportQuery implements ReportQuery
{
    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'stock.balances',
            title: 'Stok Bakiye',
            category: 'Stok',
            permission: 'stock.view',
            filters: [
                new ReportFilterDefinition('product_id', 'Ürün', 'positive_integer'),
                new ReportFilterDefinition('location_id', 'Lokasyon', 'positive_integer'),
                new ReportFilterDefinition('active_only', 'Yalnız aktif kartlar', 'boolean'),
            ],
            columns: [
                new ReportColumnDefinition('product_id', 'Ürün ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('product_code', 'Ürün Kod'),
                new ReportColumnDefinition('product_name', 'Ürün'),
                new ReportColumnDefinition('location_id', 'Lokasyon ID', 'integer', defaultVisible: false),
                new ReportColumnDefinition('location_code', 'Lokasyon Kod'),
                new ReportColumnDefinition('location_name', 'Lokasyon'),
                new ReportColumnDefinition('quantity', 'Fiziksel', 'decimal'),
                new ReportColumnDefinition('reserved', 'Rezerve', 'decimal'),
                new ReportColumnDefinition('consignment_reserved', 'Konsinye Rezerve', 'decimal'),
                new ReportColumnDefinition('quarantine', 'Karantina', 'decimal'),
                new ReportColumnDefinition('available', 'Satılabilir', 'decimal'),
                new ReportColumnDefinition('moving_average', 'Hareketli Ortalama', 'decimal', costSensitive: true),
                new ReportColumnDefinition('stock_value', 'Stok Değeri', 'decimal', costSensitive: true),
            ],
            defaultSort: [
                new ReportSort('product_code'),
                new ReportSort('location_code'),
                new ReportSort('product_id'),
                new ReportSort('location_id'),
            ],
            totals: [
                new ReportTotalDefinition('quantity', 'Fiziksel'),
                new ReportTotalDefinition('reserved', 'Rezerve'),
                new ReportTotalDefinition('consignment_reserved', 'Konsinye Rezerve'),
                new ReportTotalDefinition('quarantine', 'Karantina'),
                new ReportTotalDefinition('available', 'Satılabilir'),
                new ReportTotalDefinition('stock_value', 'Stok Değeri', costSensitive: true),
            ],
            drillDowns: [
                new ReportDrillDownDefinition(
                    key: 'product',
                    label: 'Ürün',
                    permission: 'products.view',
                    idColumn: 'product_id',
                    target: 'products',
                ),
            ],
        );
    }

    public function execute(ReportExecutionContext $context): ReportQueryResult
    {
        $base = DB::connection('period')
            ->table('stock_balances as sb')
            ->join('products as p', 'p.id', '=', 'sb.product_id')
            ->join('locations as l', 'l.id', '=', 'sb.location_id');

        if ($context->canViewCost) {
            $base->leftJoin('product_costs as pc', 'pc.product_id', '=', 'sb.product_id');
        }

        $this->applyFilters($base, $context);

        $totalRows = (clone $base)->count('sb.id');
        $rows = clone $base;

        if (! $context->hasColumn('product_id')) {
            $rows->selectRaw('sb.product_id AS product_id');
        }

        foreach ($context->columns as $column) {
            $rows->selectRaw($this->selectExpression($column));
        }

        $sortMap = $this->sortMap();
        $hasProductId = false;
        $hasLocationId = false;

        foreach ($context->sort as $sort) {
            $rows->orderBy($sortMap[$sort->key], $sort->direction);
            $hasProductId = $hasProductId || $sort->key === 'product_id';
            $hasLocationId = $hasLocationId || $sort->key === 'location_id';
        }

        if (! $hasProductId) {
            $rows->orderBy('sb.product_id');
        }

        if (! $hasLocationId) {
            $rows->orderBy('sb.location_id');
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

        if (isset($filters['product_id'])) {
            $query->where('sb.product_id', $filters['product_id']);
        }

        if (isset($filters['location_id'])) {
            $query->where('sb.location_id', $filters['location_id']);
        }

        if (($filters['active_only'] ?? false) === true) {
            $query->where('p.is_active', true)->where('l.is_active', true);
        }
    }

    private function selectExpression(string $column): string
    {
        return [
            'product_id' => 'sb.product_id AS product_id',
            'product_code' => 'p.code AS product_code',
            'product_name' => 'p.name AS product_name',
            'location_id' => 'sb.location_id AS location_id',
            'location_code' => 'l.code AS location_code',
            'location_name' => 'l.name AS location_name',
            'quantity' => 'sb.quantity::text AS quantity',
            'reserved' => 'sb.reserved::text AS reserved',
            'consignment_reserved' => 'sb.consignment_reserved::text AS consignment_reserved',
            'quarantine' => 'sb.quarantine::text AS quarantine',
            'available' => '(sb.quantity - sb.reserved - sb.consignment_reserved - sb.quarantine)::text AS available',
            'moving_average' => 'COALESCE(pc.moving_average, 0)::text AS moving_average',
            'stock_value' => 'trunc(sb.quantity * COALESCE(pc.moving_average, 0), 4)::text AS stock_value',
        ][$column];
    }

    /** @return array<string,mixed> */
    private function sortMap(): array
    {
        return [
            'product_id' => 'sb.product_id',
            'product_code' => 'p.code',
            'product_name' => 'p.name',
            'location_id' => 'sb.location_id',
            'location_code' => 'l.code',
            'location_name' => 'l.name',
            'quantity' => 'sb.quantity',
            'reserved' => 'sb.reserved',
            'consignment_reserved' => 'sb.consignment_reserved',
            'quarantine' => 'sb.quarantine',
            'available' => DB::raw('(sb.quantity - sb.reserved - sb.consignment_reserved - sb.quarantine)'),
            'moving_average' => 'pc.moving_average',
            'stock_value' => DB::raw('(sb.quantity * COALESCE(pc.moving_average, 0))'),
        ];
    }

    /** @return array<string,mixed> */
    private function totals(Builder $base, ReportExecutionContext $context): array
    {
        $expressions = [
            'quantity' => 'COALESCE(SUM(sb.quantity), 0)::text AS quantity',
            'reserved' => 'COALESCE(SUM(sb.reserved), 0)::text AS reserved',
            'consignment_reserved' => 'COALESCE(SUM(sb.consignment_reserved), 0)::text AS consignment_reserved',
            'quarantine' => 'COALESCE(SUM(sb.quarantine), 0)::text AS quarantine',
            'available' => 'COALESCE(SUM(sb.quantity - sb.reserved - sb.consignment_reserved - sb.quarantine), 0)::text AS available',
            'stock_value' => 'COALESCE(SUM(trunc(sb.quantity * COALESCE(pc.moving_average, 0), 4)), 0)::text AS stock_value',
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
