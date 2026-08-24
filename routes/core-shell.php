<?php

use App\Modules\Core\Shell\ActiveContextController;
use App\Modules\Core\Shell\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/', [ActiveContextController::class, 'entry'])->name('home');

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

    Route::view('/settings', 'settings.index')
        ->middleware('company.context')
        ->name('settings.index');
});
