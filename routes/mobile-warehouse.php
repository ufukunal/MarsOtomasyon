<?php

use App\Modules\Inventory\Mobile\MobileWarehouseController;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile/warehouse')
    ->name('mobile.warehouse.')
    ->middleware(['web', 'auth', 'company.context'])
    ->group(function (): void {
        Route::get('/', [MobileWarehouseController::class, 'index'])
            ->middleware('can:inventory.view')
            ->name('index');
        Route::post('/lookup', [MobileWarehouseController::class, 'lookup'])
            ->middleware('can:products.view')
            ->name('lookup');
        Route::post('/operations', [MobileWarehouseController::class, 'execute'])
            ->middleware('can:inventory.view')
            ->name('operations.execute');
    });
