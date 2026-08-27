<?php

use App\Modules\PurchaseReturns\PurchaseReturnController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'company.context'])
    ->prefix('purchase-returns')
    ->name('purchase-returns.')
    ->group(function (): void {
        Route::get('/', [PurchaseReturnController::class, 'index'])->middleware('can:purchase_returns.view')->name('index');
        Route::get('/create', [PurchaseReturnController::class, 'create'])->middleware('can:purchase_returns.manage')->name('create');
        Route::post('/', [PurchaseReturnController::class, 'store'])->middleware('can:purchase_returns.manage')->name('store');
        Route::get('/{purchaseReturn}', [PurchaseReturnController::class, 'show'])->whereNumber('purchaseReturn')->middleware('can:purchase_returns.view')->name('show');
        Route::post('/{purchaseReturn}/finalize', [PurchaseReturnController::class, 'finalize'])->whereNumber('purchaseReturn')->middleware('can:purchase_returns.manage')->name('finalize');
    });
