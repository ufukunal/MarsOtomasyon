<?php

use App\Modules\Quotes\QuoteApprovalController;
use App\Modules\Quotes\QuoteController;
use App\Modules\Quotes\QuoteRevisionController;
use Illuminate\Support\Facades\Route;

Route::prefix('quotes')
    ->name('quotes.')
    ->middleware(['web', 'auth', 'company.context'])
    ->group(function (): void {
        Route::get('/', [QuoteController::class, 'index'])->middleware('can:quotes.view')->name('index');
        Route::get('/create', [QuoteController::class, 'create'])->middleware('can:quotes.manage')->name('create');
        Route::post('/', [QuoteController::class, 'store'])->middleware('can:quotes.manage')->name('store');
        Route::post('/{quote}/revisions', [QuoteRevisionController::class, 'store'])
            ->whereNumber('quote')->middleware('can:quotes.manage')->name('revisions.store');
        Route::post('/{quote}/revisions/{revision}/approve', [QuoteApprovalController::class, 'approve'])
            ->whereNumber('quote')->whereNumber('revision')->middleware('can:quotes.approve')->name('revisions.approve');
        Route::post('/{quote}/revisions/{revision}/reject', [QuoteApprovalController::class, 'reject'])
            ->whereNumber('quote')->whereNumber('revision')->middleware('can:quotes.approve')->name('revisions.reject');
        Route::post('/{quote}/convert', [QuoteApprovalController::class, 'convert'])
            ->whereNumber('quote')->middleware('can:quotes.approve')->name('convert');
        Route::get('/{quote}/revisions/{revision}', [QuoteRevisionController::class, 'show'])
            ->whereNumber('quote')->whereNumber('revision')->middleware('can:quotes.view')->name('revisions.show');
        Route::get('/{quote}', [QuoteController::class, 'show'])->whereNumber('quote')->middleware('can:quotes.view')->name('show');
        Route::get('/{quote}/edit', [QuoteController::class, 'edit'])->whereNumber('quote')->middleware('can:quotes.manage')->name('edit');
        Route::put('/{quote}', [QuoteController::class, 'update'])->whereNumber('quote')->middleware('can:quotes.manage')->name('update');
        Route::post('/{quote}/cancel', [QuoteController::class, 'cancel'])->whereNumber('quote')->middleware('can:quotes.manage')->name('cancel');
    });
