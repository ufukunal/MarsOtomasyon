<?php

use App\Modules\Production\ProductionController;
use Illuminate\Support\Facades\Route;

Route::prefix('production')
    ->name('production.')
    ->middleware(['web', 'auth', 'company.context'])
    ->group(function (): void {
        Route::get('/', [ProductionController::class, 'index'])
            ->middleware('can:production.view')
            ->name('index');
        Route::get('/report', [ProductionController::class, 'report'])
            ->middleware('can:production.view')
            ->name('report');
        Route::post('/recipes', [ProductionController::class, 'storeRecipe'])
            ->middleware('can:production.manage')
            ->name('recipes.store');
        Route::post('/orders', [ProductionController::class, 'storeOrder'])
            ->middleware('can:production.manage')
            ->name('orders.store');
        Route::get('/orders/{order}', [ProductionController::class, 'show'])
            ->whereNumber('order')
            ->middleware('can:production.view')
            ->name('show');
        Route::post('/orders/{order}/issue-materials', [ProductionController::class, 'issueMaterials'])
            ->whereNumber('order')
            ->middleware('can:production.manage')
            ->name('issue-materials');
        Route::post('/orders/{order}/losses', [ProductionController::class, 'recordLoss'])
            ->whereNumber('order')
            ->middleware('can:production.manage')
            ->name('losses.store');
        Route::post('/orders/{order}/receive-output', [ProductionController::class, 'receiveOutput'])
            ->whereNumber('order')
            ->middleware('can:production.manage')
            ->name('receive-output');
        Route::post('/orders/{order}/complete', [ProductionController::class, 'complete'])
            ->whereNumber('order')
            ->middleware('can:production.manage')
            ->name('complete');
        Route::post('/orders/{order}/files', [ProductionController::class, 'upload'])
            ->whereNumber('order')
            ->middleware('can:production.manage')
            ->name('files.store');
        Route::get('/orders/{order}/files/{attachment}', [ProductionController::class, 'download'])
            ->whereNumber('order')
            ->whereNumber('attachment')
            ->middleware('can:production.view')
            ->name('files.download');
        Route::post('/orders/{order}/files/{attachment}/detach', [ProductionController::class, 'detach'])
            ->whereNumber('order')
            ->whereNumber('attachment')
            ->middleware('can:production.manage')
            ->name('files.detach');
    });
