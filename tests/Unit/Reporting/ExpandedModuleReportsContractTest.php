<?php

use App\Support\Reporting\Queries\AdvanceReportQuery;
use App\Support\Reporting\Queries\CollectionReportQuery;
use App\Support\Reporting\Queries\ExpenseReportQuery;
use App\Support\Reporting\Queries\GoodsReceiptReportQuery;
use App\Support\Reporting\Queries\PaymentReportQuery;
use App\Support\Reporting\Queries\ProductionConsumptionReportQuery;
use App\Support\Reporting\Queries\PurchaseDispatchReportQuery;
use App\Support\Reporting\Queries\PurchaseOrderReportQuery;
use App\Support\Reporting\Queries\PurchaseQuoteReportQuery;
use App\Support\Reporting\Queries\SalesDispatchReportQuery;
use App\Support\Reporting\Queries\SalesOrderReportQuery;
use App\Support\Reporting\Queries\SalesProductPerformanceReportQuery;
use App\Support\Reporting\Queries\SalesProformaReportQuery;
use App\Support\Reporting\Queries\SalesQuoteReportQuery;
use App\Support\Reporting\Queries\StockCountDifferenceReportQuery;
use App\Support\Reporting\Queries\StockMovementReportQuery;
use App\Support\Reporting\Queries\StockTransferReportQuery;
use App\Support\Reporting\Queries\WarehouseSlipReportQuery;
use App\Support\Reporting\ReportRegistry;

it('registers all expanded module reports with valid keys and sortable columns', function (): void {
    $expected = [
        'sales.quotes' => SalesQuoteReportQuery::class,
        'sales.orders' => SalesOrderReportQuery::class,
        'sales.dispatches' => SalesDispatchReportQuery::class,
        'sales.proformas' => SalesProformaReportQuery::class,
        'sales.product_performance' => SalesProductPerformanceReportQuery::class,
        'purchases.quotes' => PurchaseQuoteReportQuery::class,
        'purchases.orders' => PurchaseOrderReportQuery::class,
        'purchases.dispatches' => PurchaseDispatchReportQuery::class,
        'purchases.goods_receipts' => GoodsReceiptReportQuery::class,
        'finance.collections' => CollectionReportQuery::class,
        'finance.payments' => PaymentReportQuery::class,
        'finance.expenses' => ExpenseReportQuery::class,
        'finance.advances' => AdvanceReportQuery::class,
        'stock.movements' => StockMovementReportQuery::class,
        'stock.transfers' => StockTransferReportQuery::class,
        'stock.count_differences' => StockCountDifferenceReportQuery::class,
        'stock.warehouse_slips' => WarehouseSlipReportQuery::class,
        'production.consumption' => ProductionConsumptionReportQuery::class,
    ];

    $registered = app(ReportRegistry::class)->definitions();
    $keys = array_map(fn ($definition): string => $definition->key, $registered);

    foreach ($expected as $key => $queryClass) {
        expect(in_array($queryClass, config('reporting.queries', []), true))
            ->toBeTrue("Missing query registration: {$queryClass}");
        $definition = (new $queryClass)->definition();

        expect($definition->key)->toBe($key)
            ->and($definition->columns)->not->toBeEmpty()
            ->and($definition->defaultSort)->not->toBeEmpty()
            ->and($definition->exporters)->toContain('csv', 'xlsx', 'pdf');

        foreach ($definition->defaultSort as $sort) {
            expect($definition->columnMap())->toHaveKey($sort->key);
        }
        expect($keys)->toContain($key);
    }

    expect(count($keys))->toBe(count(array_unique($keys)));
});

it('does not aggregate unconvertible physical quantities into arbitrary monetary totals', function (): void {
    foreach ([
        new StockMovementReportQuery,
        new StockTransferReportQuery,
        new StockCountDifferenceReportQuery,
        new WarehouseSlipReportQuery,
        new ProductionConsumptionReportQuery,
    ] as $query) {
        expect($query->definition()->totals)->toBeEmpty();
    }
});

it('restricts cost columns without cost viewing permission', function (): void {
    $stock = (new StockMovementReportQuery)->definition();
    $production = (new ProductionConsumptionReportQuery)->definition();

    expect($stock->defaultColumnKeys(false))->not->toContain('unit_cost', 'total_cost')
        ->and($stock->defaultColumnKeys(true))->toContain('unit_cost', 'total_cost')
        ->and($production->defaultColumnKeys(false))->not->toContain('unit_cost', 'total_cost');
});
