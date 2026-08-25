<?php

use App\Modules\Products\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('inventory')
    ->name('inventory.')
    ->middleware(['web', 'auth', 'company.context'])
    ->group(function (): void {
        Route::get('/', [ProductController::class, 'index'])
            ->middleware('can:products.view')
            ->name('index');
        Route::get('/products/create', [ProductController::class, 'create'])
            ->middleware('can:products.manage')
            ->name('products.create');
        Route::post('/products', [ProductController::class, 'store'])
            ->middleware('can:products.manage')
            ->name('products.store');
        Route::get('/products/{product}', [ProductController::class, 'show'])
            ->whereNumber('product')
            ->middleware('can:products.view')
            ->name('products.show');
        Route::get('/products/{product}/edit', [ProductController::class, 'edit'])
            ->whereNumber('product')
            ->middleware('can:products.manage')
            ->name('products.edit');
        Route::put('/products/{product}', [ProductController::class, 'update'])
            ->whereNumber('product')
            ->middleware('can:products.manage')
            ->name('products.update');
    });
