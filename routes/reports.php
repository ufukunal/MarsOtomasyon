<?php

use App\Modules\Reports\ReportsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'company.context'])
    ->prefix('reports')
    ->name('reports.')
    ->group(function (): void {
        Route::get('/', [ReportsController::class, 'index'])
            ->middleware('can:reports.view')
            ->name('index');

        Route::get('/export', [ReportsController::class, 'export'])
            ->middleware('can:reports.view')
            ->name('export');
    });
