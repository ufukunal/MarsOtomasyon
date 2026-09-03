<?php

use App\Modules\Products\ProductInstallationController;
use Illuminate\Support\Facades\Route;

Route::prefix('inventory/products/{product}/installation')
    ->name('inventory.products.installation.')
    ->whereNumber('product')
    ->middleware(['web', 'auth', 'company.context'])
    ->group(function (): void {
        Route::get('/', [ProductInstallationController::class, 'edit'])
            ->middleware('can:products.view')
            ->name('edit');
        Route::put('/', [ProductInstallationController::class, 'update'])
            ->middleware('can:products.manage')
            ->name('update');
        Route::get('/preview', [ProductInstallationController::class, 'preview'])
            ->middleware('can:products.view')
            ->name('preview');
        Route::post('/publish', [ProductInstallationController::class, 'publish'])
            ->middleware('can:products.manage')
            ->name('publish');
        Route::get('/versions/{version}/download', [ProductInstallationController::class, 'download'])
            ->whereNumber('version')
            ->middleware('can:products.view')
            ->name('download');
    });