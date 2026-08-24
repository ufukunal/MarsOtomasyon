<?php

use App\Modules\Core\Auth\AuthenticatedSessionController;
use App\Modules\Core\Management\AuditTrailController;
use App\Modules\Core\Management\BranchManagementController;
use App\Modules\Core\Management\CompanyFileController;
use App\Modules\Core\Management\CompanySettingsController;
use App\Modules\Core\Management\CurrencyExchangeController;
use App\Modules\Core\Management\DocumentSequenceController;
use App\Modules\Core\Management\PostingPeriodController;
use App\Modules\Core\Management\RoleManagementController;
use App\Modules\Core\Management\TaxSettingsController;
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

        Route::get('/branches', [BranchManagementController::class, 'index'])
            ->middleware('can:core.branch.view')
            ->name('branches.index');
        Route::get('/branches/create', [BranchManagementController::class, 'create'])
            ->middleware('can:core.branch.manage')
            ->name('branches.create');
        Route::post('/branches', [BranchManagementController::class, 'store'])
            ->middleware('can:core.branch.manage')
            ->name('branches.store');
        Route::get('/branches/{branch}', [BranchManagementController::class, 'show'])
            ->whereNumber('branch')
            ->middleware('can:core.branch.view')
            ->name('branches.show');
        Route::get('/branches/{branch}/edit', [BranchManagementController::class, 'edit'])
            ->whereNumber('branch')
            ->middleware('can:core.branch.manage')
            ->name('branches.edit');
        Route::put('/branches/{branch}', [BranchManagementController::class, 'update'])
            ->whereNumber('branch')
            ->middleware('can:core.branch.manage')
            ->name('branches.update');
        Route::post('/branches/{branch}/select', [BranchManagementController::class, 'select'])
            ->whereNumber('branch')
            ->middleware('can:core.branch.view')
            ->name('branches.select');

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

        Route::get('/taxes', [TaxSettingsController::class, 'index'])
            ->middleware('can:core.settings.view')
            ->name('taxes.index');
        Route::get('/taxes/create', [TaxSettingsController::class, 'createTax'])
            ->middleware('can:core.settings.manage')
            ->name('taxes.create');
        Route::post('/taxes', [TaxSettingsController::class, 'storeTax'])
            ->middleware('can:core.settings.manage')
            ->name('taxes.store');
        Route::get('/taxes/{tax}', [TaxSettingsController::class, 'showTax'])
            ->whereNumber('tax')
            ->middleware('can:core.settings.view')
            ->name('taxes.show');
        Route::get('/taxes/{tax}/edit', [TaxSettingsController::class, 'editTax'])
            ->whereNumber('tax')
            ->middleware('can:core.settings.manage')
            ->name('taxes.edit');
        Route::put('/taxes/{tax}', [TaxSettingsController::class, 'updateTax'])
            ->whereNumber('tax')
            ->middleware('can:core.settings.manage')
            ->name('taxes.update');

        Route::get('/tax-zero-reasons/create', [TaxSettingsController::class, 'createZeroReason'])
            ->middleware('can:core.settings.manage')
            ->name('tax-zero-reasons.create');
        Route::post('/tax-zero-reasons', [TaxSettingsController::class, 'storeZeroReason'])
            ->middleware('can:core.settings.manage')
            ->name('tax-zero-reasons.store');
        Route::get('/tax-zero-reasons/{zeroReason}', [TaxSettingsController::class, 'showZeroReason'])
            ->whereNumber('zeroReason')
            ->middleware('can:core.settings.view')
            ->name('tax-zero-reasons.show');
        Route::get('/tax-zero-reasons/{zeroReason}/edit', [TaxSettingsController::class, 'editZeroReason'])
            ->whereNumber('zeroReason')
            ->middleware('can:core.settings.manage')
            ->name('tax-zero-reasons.edit');
        Route::put('/tax-zero-reasons/{zeroReason}', [TaxSettingsController::class, 'updateZeroReason'])
            ->whereNumber('zeroReason')
            ->middleware('can:core.settings.manage')
            ->name('tax-zero-reasons.update');

        Route::get('/exchange-rates', [CurrencyExchangeController::class, 'index'])
            ->middleware('can:core.settings.view')
            ->name('exchange-rates.index');
        Route::get('/exchange-rates/create', [CurrencyExchangeController::class, 'create'])
            ->middleware('can:core.settings.manage')
            ->name('exchange-rates.create');
        Route::post('/exchange-rates', [CurrencyExchangeController::class, 'store'])
            ->middleware('can:core.settings.manage')
            ->name('exchange-rates.store');
        Route::get('/exchange-rates/{rate}', [CurrencyExchangeController::class, 'show'])
            ->whereNumber('rate')
            ->middleware('can:core.settings.view')
            ->name('exchange-rates.show');
        Route::get('/exchange-rates/{rate}/edit', [CurrencyExchangeController::class, 'edit'])
            ->whereNumber('rate')
            ->middleware('can:core.settings.manage')
            ->name('exchange-rates.edit');
        Route::put('/exchange-rates/{rate}', [CurrencyExchangeController::class, 'update'])
            ->whereNumber('rate')
            ->middleware('can:core.settings.manage')
            ->name('exchange-rates.update');

        Route::get('/posting-periods', [PostingPeriodController::class, 'index'])
            ->middleware('can:core.settings.view')
            ->name('posting-periods.index');
        Route::get('/posting-periods/create', [PostingPeriodController::class, 'create'])
            ->middleware('can:core.settings.manage')
            ->name('posting-periods.create');
        Route::post('/posting-periods', [PostingPeriodController::class, 'store'])
            ->middleware('can:core.settings.manage')
            ->name('posting-periods.store');
        Route::get('/posting-periods/{period}', [PostingPeriodController::class, 'show'])
            ->whereNumber('period')
            ->middleware('can:core.settings.view')
            ->name('posting-periods.show');
        Route::get('/posting-periods/{period}/edit', [PostingPeriodController::class, 'edit'])
            ->whereNumber('period')
            ->middleware('can:core.settings.manage')
            ->name('posting-periods.edit');
        Route::put('/posting-periods/{period}', [PostingPeriodController::class, 'update'])
            ->whereNumber('period')
            ->middleware('can:core.settings.manage')
            ->name('posting-periods.update');
        Route::post('/posting-periods/{period}/close', [PostingPeriodController::class, 'close'])
            ->whereNumber('period')
            ->middleware('can:core.settings.manage')
            ->name('posting-periods.close');

        Route::get('/audit', [AuditTrailController::class, 'index'])
            ->middleware('can:core.settings.view')
            ->name('audit.index');
        Route::get('/audit/{audit}', [AuditTrailController::class, 'show'])
            ->whereNumber('audit')
            ->middleware('can:core.settings.view')
            ->name('audit.show');

        Route::get('/files', [CompanyFileController::class, 'index'])
            ->middleware('can:core.file.view')
            ->name('files.index');
        Route::get('/files/create', [CompanyFileController::class, 'create'])
            ->middleware('can:core.file.manage')
            ->name('files.create');
        Route::post('/files', [CompanyFileController::class, 'store'])
            ->middleware('can:core.file.manage')
            ->name('files.store');
        Route::get('/files/{attachment}', [CompanyFileController::class, 'show'])
            ->whereNumber('attachment')
            ->middleware('can:core.file.view')
            ->name('files.show');
        Route::get('/files/{attachment}/download', [CompanyFileController::class, 'download'])
            ->whereNumber('attachment')
            ->middleware('can:core.file.view')
            ->name('files.download');
        Route::post('/files/{attachment}/detach', [CompanyFileController::class, 'detach'])
            ->whereNumber('attachment')
            ->middleware('can:core.file.manage')
            ->name('files.detach');

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
