<?php

use App\Modules\Inventory\InventoryController;
use App\Modules\Inventory\StockCountController;
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

        Route::get('/stock', [InventoryController::class, 'stock'])
            ->middleware('can:inventory.view')
            ->name('stock.index');
        Route::get('/stock/movements', [InventoryController::class, 'movements'])
            ->middleware('can:inventory.view')
            ->name('stock.movements');
        Route::get('/stock/movements/create', [InventoryController::class, 'createMovement'])
            ->middleware('can:inventory.manage')
            ->name('stock.movements.create');
        Route::post('/stock/movements', [InventoryController::class, 'storeMovement'])
            ->middleware('can:inventory.manage')
            ->name('stock.movements.store');

        Route::get('/stock/counts', [StockCountController::class, 'index'])
            ->middleware('can:inventory.view')
            ->name('counts.index');
        Route::get('/stock/counts/create', [StockCountController::class, 'create'])
            ->middleware('can:inventory.manage')
            ->name('counts.create');
        Route::post('/stock/counts', [StockCountController::class, 'store'])
            ->middleware('can:inventory.manage')
            ->name('counts.store');
        Route::get('/stock/counts/{count}', [StockCountController::class, 'show'])
            ->whereNumber('count')
            ->middleware('can:inventory.view')
            ->name('counts.show');
        Route::put('/stock/counts/{count}/line', [StockCountController::class, 'setLine'])
            ->whereNumber('count')
            ->middleware('can:inventory.manage')
            ->name('counts.line.update');
        Route::post('/stock/counts/{count}/scan', [StockCountController::class, 'scan'])
            ->whereNumber('count')
            ->middleware('can:inventory.manage')
            ->name('counts.scan');
        Route::post('/stock/counts/{count}/post', [StockCountController::class, 'post'])
            ->whereNumber('count')
            ->middleware('can:inventory.manage')
            ->name('counts.post');

        Route::get('/warehouses', [InventoryController::class, 'warehouses'])
            ->middleware('can:inventory.view')
            ->name('warehouses.index');
        Route::post('/warehouses', [InventoryController::class, 'storeWarehouse'])
            ->middleware('can:inventory.manage')
            ->name('warehouses.store');
        Route::post('/warehouses/{warehouse}/locations', [InventoryController::class, 'storeLocation'])
            ->whereNumber('warehouse')
            ->middleware('can:inventory.manage')
            ->name('warehouses.locations.store');

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

        Route::post('/products/{product}/resources/media/{file}/main', [ProductResourcesController::class, 'setMainMedia'])
            ->whereNumber('product')
            ->whereNumber('file')
            ->middleware('can:products.manage')
            ->name('products.resources.media.main');
        Route::put('/products/{product}/resources/media-order', [ProductResourcesController::class, 'reorderMedia'])
            ->whereNumber('product')
            ->middleware('can:products.manage')
            ->name('products.resources.media.order');
        Route::put('/products/{product}/resources/media/{file}/destinations', [ProductResourcesController::class, 'updateMediaDestinations'])
            ->whereNumber('product')
            ->whereNumber('file')
            ->middleware('can:products.manage')
            ->name('products.resources.media.destinations');
        Route::put('/products/{product}/resources/media/{file}/transform', [ProductResourcesController::class, 'updateMediaTransform'])
            ->whereNumber('product')
            ->whereNumber('file')
            ->middleware('can:products.manage')
            ->name('products.resources.media.transform');
        Route::put('/products/{product}/resources/media/{file}/provider-validation', [ProductResourcesController::class, 'updateMediaProviderValidation'])
            ->whereNumber('product')
            ->whereNumber('file')
            ->middleware('can:products.manage')
            ->name('products.resources.media.provider-validation');
        Route::post('/products/{product}/resources/media/{file}/copy', [ProductResourcesController::class, 'copyMedia'])
            ->whereNumber('product')
            ->whereNumber('file')
            ->middleware('can:products.manage')
            ->name('products.resources.media.copy');
        Route::post('/products/{product}/resources/media/{file}/move', [ProductResourcesController::class, 'moveMedia'])
            ->whereNumber('product')
            ->whereNumber('file')
            ->middleware('can:products.manage')
            ->name('products.resources.media.move');
        Route::post('/products/{product}/resources/media/{file}/quarantine', [ProductResourcesController::class, 'quarantineMedia'])
            ->whereNumber('product')
            ->whereNumber('file')
            ->middleware('can:products.manage')
            ->name('products.resources.media.quarantine');
        Route::post('/products/{product}/resources/media/{file}/release-quarantine', [ProductResourcesController::class, 'releaseMediaQuarantine'])
            ->whereNumber('product')
            ->whereNumber('file')
            ->middleware('can:products.manage')
            ->name('products.resources.media.release-quarantine');

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
