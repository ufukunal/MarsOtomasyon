<?php

use App\Modules\Products\CategoryController;
use App\Modules\Products\ProductController;
use App\Modules\Products\ProductResourcesController;
use App\Modules\Products\UnitController;
use Illuminate\Support\Facades\Route;

Route::prefix('inventory')
    ->name('inventory.')
    ->middleware(['web', 'auth', 'company.context'])
    ->group(function (): void {
        Route::get('/', [ProductController::class, 'index'])
            ->middleware('can:products.view')
            ->name('index');

        Route::get('/categories', [CategoryController::class, 'index'])
            ->middleware('can:products.view')
            ->name('categories.index');
        Route::get('/categories/create', [CategoryController::class, 'create'])
            ->middleware('can:products.manage')
            ->name('categories.create');
        Route::post('/categories', [CategoryController::class, 'store'])
            ->middleware('can:products.manage')
            ->name('categories.store');
        Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])
            ->whereNumber('category')
            ->middleware('can:products.manage')
            ->name('categories.edit');
        Route::put('/categories/{category}', [CategoryController::class, 'update'])
            ->whereNumber('category')
            ->middleware('can:products.manage')
            ->name('categories.update');

        Route::get('/units', [UnitController::class, 'index'])
            ->middleware('can:products.view')
            ->name('units.index');
        Route::get('/units/create', [UnitController::class, 'create'])
            ->middleware('can:products.manage')
            ->name('units.create');
        Route::post('/units', [UnitController::class, 'store'])
            ->middleware('can:products.manage')
            ->name('units.store');
        Route::get('/units/{unit}/edit', [UnitController::class, 'edit'])
            ->whereNumber('unit')
            ->middleware('can:products.manage')
            ->name('units.edit');
        Route::put('/units/{unit}', [UnitController::class, 'update'])
            ->whereNumber('unit')
            ->middleware('can:products.manage')
            ->name('units.update');

        Route::get('/products/create', [ProductController::class, 'create'])
            ->middleware('can:products.manage')
            ->name('products.create');
        Route::post('/products', [ProductController::class, 'store'])
            ->middleware('can:products.manage')
            ->name('products.store');
        Route::get('/products/{product}/resources', [ProductResourcesController::class, 'edit'])
            ->whereNumber('product')
            ->middleware('can:products.view')
            ->name('products.resources.edit');
        Route::put('/products/{product}/resources/suppliers', [ProductResourcesController::class, 'updateSuppliers'])
            ->whereNumber('product')
            ->middleware('can:products.manage')
            ->name('products.resources.suppliers.update');
        Route::post('/products/{product}/resources/files', [ProductResourcesController::class, 'uploadFile'])
            ->whereNumber('product')
            ->middleware('can:products.manage')
            ->name('products.resources.files.store');
        Route::get('/products/{product}/resources/files/{file}/download', [ProductResourcesController::class, 'downloadFile'])
            ->whereNumber('product')
            ->whereNumber('file')
            ->middleware('can:products.view')
            ->name('products.resources.files.download');
        Route::post('/products/{product}/resources/files/{file}/detach', [ProductResourcesController::class, 'detachFile'])
            ->whereNumber('product')
            ->whereNumber('file')
            ->middleware('can:products.manage')
            ->name('products.resources.files.detach');
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
