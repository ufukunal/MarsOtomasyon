<?php

use App\Modules\Quotes\QuoteController;
use Illuminate\Support\Facades\Route;

Route::prefix('quotes')
    ->name('quotes.')
    ->middleware(['web', 'auth', 'company.context'])
    ->group(function (): void {
        Route::get('/', [QuoteController::class, 'index'])->middleware('can:quotes.view')->name('index');
        Route::get('/create', [QuoteController::class, 'create'])->middleware('can:quotes.manage')->name('create');
        Route::post('/', [QuoteController::class, 'store'])->middleware('can:quotes.manage')->name('store');
        Route::get('/{quote}', [QuoteController::class, 'show'])->whereNumber('quote')->middleware('can:quotes.view')->name('show');
        Route::get('/{quote}/edit', [QuoteController::class, 'edit'])->whereNumber('quote')->middleware('can:quotes.manage')->name('edit');
        Route::put('/{quote}', [QuoteController::class, 'update'])->whereNumber('quote')->middleware('can:quotes.manage')->name('update');
        Route::post('/{quote}/cancel', [QuoteController::class, 'cancel'])->whereNumber('quote')->middleware('can:quotes.manage')->name('cancel');
    });
