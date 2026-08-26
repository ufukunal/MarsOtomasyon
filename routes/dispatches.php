<?php

use App\Modules\Dispatches\DispatchController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'company.context'])
    ->prefix('dispatches')
    ->name('dispatches.')
    ->group(function (): void {
        Route::get('/', [DispatchController::class, 'index'])->middleware('can:dispatches.view')->name('index');
        Route::get('/create', [DispatchController::class, 'create'])->middleware('can:dispatches.manage')->name('create');
        Route::post('/', [DispatchController::class, 'store'])->middleware('can:dispatches.manage')->name('store');
        Route::post('/{dispatch}/finalize', [DispatchController::class, 'finalize'])
            ->whereNumber('dispatch')
            ->middleware('can:dispatches.manage')
            ->name('finalize');
        Route::get('/{dispatch}', [DispatchController::class, 'show'])->whereNumber('dispatch')->middleware('can:dispatches.view')->name('show');
    });
