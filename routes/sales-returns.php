<?php

use App\Modules\SalesReturns\SalesReturnController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'company.context'])
    ->prefix('returns')
    ->name('returns.')
    ->group(function (): void {
        Route::get('/', [SalesReturnController::class, 'index'])->middleware('can:sales_returns.view')->name('index');
        Route::get('/create', [SalesReturnController::class, 'create'])->middleware('can:sales_returns.manage')->name('create');
        Route::post('/', [SalesReturnController::class, 'store'])->middleware('can:sales_returns.manage')->name('store');
        Route::get('/{salesReturn}', [SalesReturnController::class, 'show'])->whereNumber('salesReturn')->middleware('can:sales_returns.view')->name('show');
        Route::post('/{salesReturn}/authorize', [SalesReturnController::class, 'authorize'])->whereNumber('salesReturn')->middleware('can:sales_returns.manage')->name('authorize');
        Route::post('/{salesReturn}/receive', [SalesReturnController::class, 'receive'])->whereNumber('salesReturn')->middleware('can:sales_returns.manage')->name('receive');
        Route::post('/{salesReturn}/complete', [SalesReturnController::class, 'complete'])->whereNumber('salesReturn')->middleware('can:sales_returns.manage')->name('complete');
        Route::post('/{salesReturn}/cancel', [SalesReturnController::class, 'cancel'])->whereNumber('salesReturn')->middleware('can:sales_returns.manage')->name('cancel');
    });
