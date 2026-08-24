<?php

use App\Modules\Core\Auth\AuthenticatedSessionController;
use App\Modules\Core\Management\CompanySettingsController;
use App\Modules\Core\Management\DocumentSequenceController;
use App\Modules\Core\Management\RoleManagementController;
use App\Modules\Core\Management\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::prefix('settings')
    ->name('settings.')
    ->middleware(['auth', 'company.context'])
    ->group(function (): void {
        Route::get('/company', [CompanySettingsController::class, 'show'])
            ->middleware('can:core.settings.view')
            ->name('company.show');
        Route::get('/company/edit', [CompanySettingsController::class, 'edit'])
            ->middleware('can:core.settings.manage')
            ->name('company.edit');
        Route::put('/company', [CompanySettingsController::class, 'update'])
            ->middleware('can:core.settings.manage')
            ->name('company.update');

        Route::get('/numbering', [DocumentSequenceController::class, 'index'])
            ->middleware('can:core.settings.view')
            ->name('numbering.index');
        Route::get('/numbering/create', [DocumentSequenceController::class, 'create'])
            ->middleware('can:core.settings.manage')
            ->name('numbering.create');
        Route::post('/numbering', [DocumentSequenceController::class, 'store'])
            ->middleware('can:core.settings.manage')
            ->name('numbering.store');
        Route::get('/numbering/{sequence}', [DocumentSequenceController::class, 'show'])
            ->whereNumber('sequence')
            ->middleware('can:core.settings.view')
            ->name('numbering.show');
        Route::get('/numbering/{sequence}/edit', [DocumentSequenceController::class, 'edit'])
            ->whereNumber('sequence')
            ->middleware('can:core.settings.manage')
            ->name('numbering.edit');
        Route::put('/numbering/{sequence}', [DocumentSequenceController::class, 'update'])
            ->whereNumber('sequence')
            ->middleware('can:core.settings.manage')
            ->name('numbering.update');

        Route::get('/users', [UserManagementController::class, 'index'])
            ->middleware('can:core.user.view')
            ->name('users.index');
        Route::get('/users/create', [UserManagementController::class, 'create'])
            ->middleware('can:core.user.manage')
            ->name('users.create');
        Route::post('/users', [UserManagementController::class, 'store'])
            ->middleware('can:core.user.manage')
            ->name('users.store');
        Route::get('/users/{membership}', [UserManagementController::class, 'show'])
            ->whereNumber('membership')
            ->middleware('can:core.user.view')
            ->name('users.show');
        Route::get('/users/{membership}/edit', [UserManagementController::class, 'edit'])
            ->whereNumber('membership')
            ->middleware('can:core.user.manage')
            ->name('users.edit');
        Route::put('/users/{membership}', [UserManagementController::class, 'update'])
            ->whereNumber('membership')
            ->middleware('can:core.user.manage')
            ->name('users.update');

        Route::get('/roles', [RoleManagementController::class, 'index'])
            ->middleware('can:core.role.view')
            ->name('roles.index');
        Route::get('/roles/create', [RoleManagementController::class, 'create'])
            ->middleware('can:core.role.manage')
            ->name('roles.create');
        Route::post('/roles', [RoleManagementController::class, 'store'])
            ->middleware('can:core.role.manage')
            ->name('roles.store');
        Route::get('/roles/{role}', [RoleManagementController::class, 'show'])
            ->whereNumber('role')
            ->middleware('can:core.role.view')
            ->name('roles.show');
        Route::get('/roles/{role}/edit', [RoleManagementController::class, 'edit'])
            ->whereNumber('role')
            ->middleware('can:core.role.manage')
            ->name('roles.edit');
        Route::put('/roles/{role}', [RoleManagementController::class, 'update'])
            ->whereNumber('role')
            ->middleware('can:core.role.manage')
            ->name('roles.update');
    });

Route::view('/', 'welcome')->name('home');
