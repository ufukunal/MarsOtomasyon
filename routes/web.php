<?php

use App\Livewire\Pages\Auth\ForgotPassword;
use App\Livewire\Pages\Auth\Login;
use App\Livewire\Pages\Auth\PeriodSelection;
use App\Livewire\Pages\Auth\ResetPassword;
use App\Livewire\Pages\Settings\IntegrityReport;
use App\Livewire\Pages\Settings\Periods;
use App\Livewire\Pages\Setup\CompanyWizard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->middleware('auth')->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/giris', Login::class)->name('login');
    Route::get('/parola-unuttum', ForgotPassword::class)->name('password.request');
    Route::get('/parola-sifirla/{token}', ResetPassword::class)->name('password.reset');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/secim', PeriodSelection::class)->name('period.select');
    Route::get('/ayarlar/donemler', Periods::class)->name('settings.periods');
    Route::get('/ayarlar/butunluk', IntegrityReport::class)->name('settings.integrity');

    Route::post('/cikis', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});

Route::get('/kurulum', CompanyWizard::class)->name('setup');
