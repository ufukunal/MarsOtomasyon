<?php

use App\Modules\SalesOrders\SalesOrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'company.context'])->group(function (): void {
    Route::get('/sales', fn () => redirect()->route('sales-orders.index'))
        ->middleware('can:sales_orders.view')
        ->name('sales.index');

    Route::prefix('sales-orders')
        ->name('sales-orders.')
        ->group(function (): void {
            Route::get('/', [SalesOrderController::class, 'index'])->middleware('can:sales_orders.view')->name('index');
            Route::get('/create', [SalesOrderController::class, 'create'])->middleware('can:sales_orders.manage')->name('create');
            Route::get('/product-search', [SalesOrderController::class, 'productSearch'])->middleware('can:sales_orders.manage')->name('product-search');
            Route::post('/', [SalesOrderController::class, 'store'])->middleware('can:sales_orders.manage')->name('store');
            Route::get('/{salesOrder}', [SalesOrderController::class, 'show'])->whereNumber('salesOrder')->middleware('can:sales_orders.view')->name('show');
            Route::get('/{salesOrder}/edit', [SalesOrderController::class, 'edit'])->whereNumber('salesOrder')->middleware('can:sales_orders.manage')->name('edit');
            Route::put('/{salesOrder}', [SalesOrderController::class, 'update'])->whereNumber('salesOrder')->middleware('can:sales_orders.manage')->name('update');
        });
});
