<?php

use App\Modules\Accounts\AccountB2BPolicyController;
use App\Modules\Accounts\AccountB2BUserController;
use App\Modules\Accounts\AccountController;
use App\Modules\Accounts\AccountProfileController;
use App\Modules\Accounts\AccountRecordsController;
use App\Modules\Accounts\AccountStatementController;
use Illuminate\Support\Facades\Route;

Route::get('/accounts', fn () => redirect()->route('customers.index'))
    ->middleware(['web', 'auth', 'company.context', 'can:accounts.view'])
    ->name('accounts.legacy');

Route::prefix('customers')
    ->name('customers.')
    ->middleware(['web', 'auth', 'company.context'])
    ->group(function (): void {
        Route::get('/', [AccountController::class, 'index'])->middleware('can:accounts.view')->name('index');
        Route::get('/create', [AccountController::class, 'create'])->middleware('can:accounts.manage')->name('create');
        Route::post('/', [AccountController::class, 'store'])->middleware('can:accounts.manage')->name('store');
        Route::get('/{account}/profile/edit', [AccountProfileController::class, 'edit'])->whereNumber('account')->middleware('can:accounts.manage')->name('profile.edit');
        Route::put('/{account}/profile', [AccountProfileController::class, 'update'])->whereNumber('account')->middleware('can:accounts.manage')->name('profile.update');
        Route::get('/{account}/records/edit', [AccountRecordsController::class, 'edit'])->whereNumber('account')->middleware('can:accounts.manage')->name('records.edit');
        Route::put('/{account}/records', [AccountRecordsController::class, 'update'])->whereNumber('account')->middleware('can:accounts.manage')->name('records.update');
        Route::get('/{account}/b2b/edit', [AccountB2BPolicyController::class, 'edit'])->whereNumber('account')->middleware('can:accounts.manage')->name('b2b.edit');
        Route::put('/{account}/b2b', [AccountB2BPolicyController::class, 'update'])->whereNumber('account')->middleware('can:accounts.manage')->name('b2b.update');
        Route::post('/{account}/b2b/users', [AccountB2BUserController::class, 'store'])->whereNumber('account')->middleware('can:accounts.manage')->name('b2b.users.store');
        Route::put('/{account}/b2b/users/{user}', [AccountB2BUserController::class, 'update'])->whereNumber('account')->where('user', '[0-9A-HJKMNP-TV-Z]{26}')->middleware('can:accounts.manage')->name('b2b.users.update');
        Route::post('/{account}/files', [AccountRecordsController::class, 'uploadFile'])->whereNumber('account')->middleware('can:accounts.manage')->name('files.store');
        Route::get('/{account}/files/{attachment}/download', [AccountRecordsController::class, 'downloadFile'])->whereNumber('account')->whereNumber('attachment')->middleware('can:accounts.view')->name('files.download');
        Route::post('/{account}/files/{attachment}/detach', [AccountRecordsController::class, 'detachFile'])->whereNumber('account')->whereNumber('attachment')->middleware('can:accounts.manage')->name('files.detach');
        Route::get('/{account}/statement', [AccountStatementController::class, 'index'])->whereNumber('account')->middleware('can:accounts.view')->name('statement.index');
        Route::get('/{account}', [AccountController::class, 'show'])->whereNumber('account')->middleware('can:accounts.view')->name('show');
        Route::get('/{account}/edit', [AccountController::class, 'edit'])->whereNumber('account')->middleware('can:accounts.manage')->name('edit');
        Route::put('/{account}', [AccountController::class, 'update'])->whereNumber('account')->middleware('can:accounts.manage')->name('update');
    });
