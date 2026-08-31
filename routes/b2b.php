<?php

use App\Modules\B2B\Auth\B2BAuthenticatedSessionController;
use App\Modules\B2B\Portal\B2BDashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('b2b')
    ->name('b2b.')
    ->middleware('web')
    ->group(function (): void {
        Route::get('/login', [B2BAuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('/login', [B2BAuthenticatedSessionController::class, 'store'])->name('login.store');

        Route::middleware('b2b.auth')->group(function (): void {
            Route::get('/', B2BDashboardController::class)->name('home');
            Route::post('/logout', [B2BAuthenticatedSessionController::class, 'destroy'])->name('logout');
        });
    });
