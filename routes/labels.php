<?php

use App\Modules\Products\Labels\Http\Controllers\LabelController;
use Illuminate\Support\Facades\Route;

Route::prefix('inventory/labels')
    ->name('inventory.labels.')
    ->middleware(['web', 'auth', 'company.context'])
    ->group(function (): void {
        Route::post('/templates', [LabelController::class, 'storeTemplate'])
            ->middleware('can:products.manage')
            ->name('templates.store');
        Route::post('/printers', [LabelController::class, 'storePrinterProfile'])
            ->middleware('can:products.manage')
            ->name('printers.store');
        Route::post('/render', [LabelController::class, 'render'])
            ->middleware('can:products.manage')
            ->name('render');
        Route::post('/{labelPrint}/reprint', [LabelController::class, 'reprint'])
            ->whereNumber('labelPrint')
            ->middleware('can:products.manage')
            ->name('reprint');
        Route::get('/{labelPrint}/output', [LabelController::class, 'output'])
            ->whereNumber('labelPrint')
            ->middleware('can:products.view')
            ->name('output');
    });
