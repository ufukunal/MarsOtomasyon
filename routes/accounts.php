<?php

use App\Modules\Accounts\AccountController;
use Illuminate\Support\Facades\Route;

Route::prefix('customers')
    ->name('customers.')
    ->middleware(['web', 'auth', 'company.context'])
    ->group(function (): void {
        Route::get('/', [AccountController::class, 'index'])
            ->middleware('can:accounts.view')
            ->name('index');
        Route::get('/create', [AccountController::class, 'create'])
            ->middleware('can:accounts.manage')
            ->name('create');
        Route::post('/', [AccountController::class, 'store'])
            ->middleware('can:accounts.manage')
            ->name('store');
        Route::get('/{account}', [AccountController::class, 'show'])
            ->whereNumber('account')
            ->middleware('can:accounts.view')
            ->name('show');
        Route::get('/{account}/edit', [AccountController::class, 'edit'])
            ->whereNumber('account')
            ->middleware('can:accounts.manage')
            ->name('edit');
        Route::put('/{account}', [AccountController::class, 'update'])
            ->whereNumber('account')
            ->middleware('can:accounts.manage')
            ->name('update');
    });
