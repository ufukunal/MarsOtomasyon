<?php

use App\Modules\GoodsReceipts\GoodsReceiptController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'company.context'])
    ->prefix('goods-receipts')
    ->name('goods-receipts.')
    ->group(function (): void {
        Route::get('/', [GoodsReceiptController::class, 'index'])->middleware('can:goods_receipts.view')->name('index');
        Route::get('/create', [GoodsReceiptController::class, 'create'])->middleware('can:goods_receipts.manage')->name('create');
        Route::post('/', [GoodsReceiptController::class, 'store'])->middleware('can:goods_receipts.manage')->name('store');
        Route::get('/{goodsReceipt}', [GoodsReceiptController::class, 'show'])->whereNumber('goodsReceipt')->middleware('can:goods_receipts.view')->name('show');
        Route::get('/{goodsReceipt}/edit', [GoodsReceiptController::class, 'edit'])->whereNumber('goodsReceipt')->middleware('can:goods_receipts.manage')->name('edit');
        Route::put('/{goodsReceipt}', [GoodsReceiptController::class, 'update'])->whereNumber('goodsReceipt')->middleware('can:goods_receipts.manage')->name('update');
        Route::post('/{goodsReceipt}/finalize', [GoodsReceiptController::class, 'finalize'])->whereNumber('goodsReceipt')->middleware('can:goods_receipts.manage')->name('finalize');
    });
