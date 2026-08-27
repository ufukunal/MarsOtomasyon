<?php

use App\Modules\Core\Enums\PermissionKey;
use App\Modules\PurchaseOrders\PurchaseOrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'company.context'])->group(function (): void {
    Route::get('/purchasing', [PurchaseOrderController::class, 'index'])
        ->middleware('can:'.PermissionKey::PurchaseOrderView->value)
        ->name('purchasing.index');

    Route::prefix('purchase-orders')
        ->name('purchase-orders.')
        ->group(function (): void {
            Route::get('/', [PurchaseOrderController::class, 'index'])->middleware('can:purchase_orders.view')->name('index');
            Route::get('/create', [PurchaseOrderController::class, 'create'])->middleware('can:purchase_orders.manage')->name('create');
            Route::get('/product-search', [PurchaseOrderController::class, 'productSearch'])->middleware('can:purchase_orders.manage')->name('product-search');
            Route::post('/', [PurchaseOrderController::class, 'store'])->middleware('can:purchase_orders.manage')->name('store');
            Route::get('/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->whereNumber('purchaseOrder')->middleware('can:purchase_orders.view')->name('show');
            Route::get('/{purchaseOrder}/edit', [PurchaseOrderController::class, 'edit'])->whereNumber('purchaseOrder')->middleware('can:purchase_orders.manage')->name('edit');
            Route::put('/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->whereNumber('purchaseOrder')->middleware('can:purchase_orders.manage')->name('update');
        });
});
