<?php

use App\Modules\B2B\Auth\B2BAuthenticatedSessionController;
use App\Modules\B2B\Auth\B2BPasswordResetController;
use App\Modules\B2B\Portal\B2BAddressController;
use App\Modules\B2B\Portal\B2BCartController;
use App\Modules\B2B\Portal\B2BCatalogController;
use App\Modules\B2B\Portal\B2BDashboardController;
use App\Modules\B2B\Portal\B2BInvoiceController;
use App\Modules\B2B\Portal\B2BOrderController;
use App\Modules\B2B\Portal\B2BStatementController;
use Illuminate\Support\Facades\Route;

Route::prefix('b2b')->name('b2b.')->middleware('web')->group(function (): void {
    Route::get('/login', [B2BAuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [B2BAuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/password/forgot', [B2BPasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('/password/forgot', [B2BPasswordResetController::class, 'requestLink'])->name('password.email');
    Route::get('/password/reset/{companyCode}/{user}/{token}', [B2BPasswordResetController::class, 'resetForm'])
        ->where('user', '[0-9A-HJKMNP-TV-Z]{26}')->where('token', '[A-Za-z0-9]{64}')->name('password.reset');
    Route::post('/password/reset/{companyCode}/{user}/{token}', [B2BPasswordResetController::class, 'reset'])
        ->where('user', '[0-9A-HJKMNP-TV-Z]{26}')->where('token', '[A-Za-z0-9]{64}')->name('password.update');

    Route::middleware('b2b.auth')->group(function (): void {
        Route::get('/', B2BDashboardController::class)->name('home');
        Route::post('/logout', [B2BAuthenticatedSessionController::class, 'destroy'])->name('logout');
        Route::get('/catalog', [B2BCatalogController::class, 'index'])->name('catalog.index');
        Route::get('/cart', [B2BCartController::class, 'index'])->name('cart.index');
        Route::post('/cart', [B2BCartController::class, 'store'])->name('cart.store');
        Route::put('/cart/{productCode}', [B2BCartController::class, 'update'])->name('cart.update');
        Route::delete('/cart/{productCode}', [B2BCartController::class, 'destroy'])->name('cart.destroy');
        Route::post('/orders', [B2BOrderController::class, 'submit'])->name('orders.submit');
        Route::get('/orders', [B2BOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [B2BOrderController::class, 'show'])->where('order', '[A-Za-z0-9._-]+')->name('orders.show');
        Route::get('/invoices', [B2BInvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/{invoice}/download', [B2BInvoiceController::class, 'download'])->where('invoice', '[A-Za-z0-9._-]+')->name('invoices.download');
        Route::get('/statement', B2BStatementController::class)->name('statement');
        Route::post('/addresses', [B2BAddressController::class, 'store'])->name('addresses.store');
        Route::put('/addresses/{address}', [B2BAddressController::class, 'update'])->where('address', '[0-9A-HJKMNP-TV-Z]{26}')->name('addresses.update');
        Route::delete('/addresses/{address}', [B2BAddressController::class, 'destroy'])->where('address', '[0-9A-HJKMNP-TV-Z]{26}')->name('addresses.destroy');
    });
});
