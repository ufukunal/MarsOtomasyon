<?php

use App\Modules\Reports\ReportsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'company.context'])
    ->prefix('reports')
    ->name('reports.')
    ->group(function (): void {
        Route::get('/', [ReportsController::class, 'index'])->middleware('can:reports.view')->name('index');
        Route::get('/catalog', [ReportsController::class, 'catalog'])->middleware('can:reports.view')->name('catalog');
        Route::get('/catalog/{reportKey}', [ReportsController::class, 'show'])->middleware('can:reports.view')->where('reportKey', '[A-Z]{3}-[0-9]{2}')->name('show');
        Route::get('/export', [ReportsController::class, 'export'])->middleware('can:reports.view')->name('export');
    });
