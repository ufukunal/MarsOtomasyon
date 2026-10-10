<?php

use App\Livewire\Pages\Auth\Login;
use App\Livewire\Pages\Auth\PeriodSelection;
use Illuminate\Support\Facades\Route;

it('disallows dashboard and period selection for anonymous web sessions', function (): void {
    $routes = Route::getRoutes();
    foreach (['home', 'period.select', 'settings.periods'] as $name) {
        expect($routes->getByName($name)->gatherMiddleware())->toContain('auth');
    }
});

it('keeps login credentials and explicit remember preference typed', function (): void {
    $login = new Login;
    expect($login->email)->toBe('')
        ->and($login->password)->toBe('')
        ->and($login->remember)->toBeFalse();
});

it('starts the period selector with nullable company and period choices', function (): void {
    $selector = new PeriodSelection;
    expect($selector->companyId)->toBeNull()
        ->and($selector->periodId)->toBeNull();
});
