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
use App\Support\Stock\StockStatusQuery;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class StockBalanceReportQuery implements ReportQuery
{
    public function __construct(private readonly StockStatusQuery $stockStatus) {}

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
                new ReportColumnDefinition('stock_status', 'Durum'),
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
        $base = $this->stockStatus
            ->build($context->canViewCost)
            ->toBase();

        $this->applyFilters($base, $context);

        $totalRows = (clone $base)->count();
        $rows = clone $base;

        if (! $context->hasColumn('product_id')) {
            $rows->selectRaw('product_id AS product_id');
        }

        if (! $context->hasColumn('location_id')) {
            $rows->selectRaw('location_id AS location_id');
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
            $rows->orderBy('product_id');
        }

        if (! $hasLocationId) {
            $rows->orderBy('location_id');
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
            $query->where('product_id', $filters['product_id']);
        }

        if (isset($filters['location_id'])) {
            $query->where('location_id', $filters['location_id']);
        }

        if (($filters['active_only'] ?? false) === true) {
            $query->where('product_is_active', true)
                ->where('location_is_active', true);
        }
    }

    private function selectExpression(string $column): string
    {
        return [
            'product_id' => 'product_id AS product_id',
            'product_code' => 'product_code AS product_code',
            'product_name' => 'product_name AS product_name',
            'location_id' => 'location_id AS location_id',
            'location_code' => 'location_code AS location_code',
            'location_name' => 'location_name AS location_name',
            'quantity' => 'quantity::text AS quantity',
            'reserved' => 'reserved::text AS reserved',
            'consignment_reserved' => 'consignment_reserved::text AS consignment_reserved',
            'quarantine' => 'quarantine::text AS quarantine',
            'available' => 'available::text AS available',
            'stock_status' => 'stock_status AS stock_status',
            'moving_average' => 'moving_average::text AS moving_average',
            'stock_value' => 'stock_value::text AS stock_value',
        ][$column];
    }

    /** @return array<string,mixed> */
    private function sortMap(): array
    {
        return [
            'product_id' => 'product_id',
            'product_code' => 'product_code',
            'product_name' => 'product_name',
            'location_id' => 'location_id',
            'location_code' => 'location_code',
            'location_name' => 'location_name',
            'quantity' => 'quantity',
            'reserved' => 'reserved',
            'consignment_reserved' => 'consignment_reserved',
            'quarantine' => 'quarantine',
            'available' => DB::raw('available::numeric'),
            'stock_status' => 'stock_status',
            'moving_average' => DB::raw('moving_average::numeric'),
            'stock_value' => DB::raw('stock_value::numeric'),
        ];
    }

    /** @return array<string,mixed> */
    private function totals(Builder $base, ReportExecutionContext $context): array
    {
        if (! $context->hasTotal('stock_value')) {
            return [];
        }

        $value = (clone $base)
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN product_kind <> 'set' THEN COALESCE(stock_value::numeric, 0) ELSE 0 END), 0)::text AS stock_value",
            )
            ->value('stock_value');

        return ['stock_value' => (string) ($value ?? '0')];
    }
}
