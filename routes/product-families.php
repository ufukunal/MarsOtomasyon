<?php

use App\Modules\Products\ProductFamilyController;
use Illuminate\Support\Facades\Route;

Route::prefix('inventory/product-families')
    ->name('inventory.product-families.')
    ->middleware(['web', 'auth', 'company.context'])
    ->group(function (): void {
        Route::get('/', [ProductFamilyController::class, 'index'])->middleware('can:products.view')->name('index');
        Route::get('/data', [ProductFamilyController::class, 'data'])->middleware('can:products.view')->name('data');
        Route::get('/create', [ProductFamilyController::class, 'create'])->middleware('can:products.manage')->name('create');
        Route::post('/', [ProductFamilyController::class, 'store'])->middleware('can:products.manage')->name('store');
        Route::get('/{family}', [ProductFamilyController::class, 'show'])->whereNumber('family')->middleware('can:products.view')->name('show');
        Route::get('/{family}/edit', [ProductFamilyController::class, 'edit'])->whereNumber('family')->middleware('can:products.manage')->name('edit');
        Route::put('/{family}', [ProductFamilyController::class, 'update'])->whereNumber('family')->middleware('can:products.manage')->name('update');
        Route::delete('/{family}', [ProductFamilyController::class, 'destroy'])->whereNumber('family')->middleware('can:products.manage')->name('destroy');
        Route::post('/{family}/dimensions', [ProductFamilyController::class, 'storeDimension'])->whereNumber('family')->middleware('can:products.manage')->name('dimensions.store');
        Route::post('/{family}/dimensions/{dimension}/values', [ProductFamilyController::class, 'storeValue'])->whereNumber('family')->whereNumber('dimension')->middleware('can:products.manage')->name('values.store');
        Route::post('/{family}/variants', [ProductFamilyController::class, 'assignVariant'])->whereNumber('family')->middleware('can:products.manage')->name('variants.store');
        Route::post('/{family}/channel-mappings', [ProductFamilyController::class, 'mapChannel'])->whereNumber('family')->middleware('can:products.manage')->name('channel-mappings.store');
        Route::post('/{family}/media', [ProductFamilyController::class, 'linkMedia'])->whereNumber('family')->middleware('can:products.manage')->name('media.store');
        Route::post('/{family}/media/{attachment}/hero', [ProductFamilyController::class, 'setHero'])->whereNumber('family')->whereNumber('attachment')->middleware('can:products.manage')->name('media.hero');
        Route::post('/{family}/media/{attachment}/detach', [ProductFamilyController::class, 'detachMedia'])->whereNumber('family')->whereNumber('attachment')->middleware('can:products.manage')->name('media.detach');
    });
