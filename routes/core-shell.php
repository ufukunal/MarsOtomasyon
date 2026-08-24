<?php

use App\Modules\Core\Shell\ActiveContextController;
use App\Modules\Core\Shell\GlobalSearchController;
use App\Modules\Core\Shell\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('/', [ActiveContextController::class, 'entry'])->name('entry');

    Route::get('/context/companies', [ActiveContextController::class, 'companies'])
        ->name('context.companies');
    Route::post('/context/companies/{company}', [ActiveContextController::class, 'selectCompany'])
        ->whereNumber('company')
        ->name('context.companies.select');

    Route::get('/workspace', [WorkspaceController::class, 'index'])
        ->middleware('company.context')
        ->name('workspace');
    Route::post('/context/branch', [ActiveContextController::class, 'selectBranch'])
        ->middleware('company.context')
        ->name('context.branches.select');
    Route::get('/search', GlobalSearchController::class)
        ->middleware('company.context')
        ->name('search');

    Route::view('/settings', 'settings.index')
        ->middleware('company.context')
        ->name('settings.index');
});
