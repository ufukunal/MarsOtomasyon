<?php

use Illuminate\Support\Facades\Route;

it('v2 highly sensitive UI routes all require authentication at router boundary', function () {
    $names = [
        'sales.invoices.index', 'sales.orders.index', 'purchases.invoices.index',
        'purchases.orders.index', 'finance.accounts', 'finance.bank-statements',
        'finance.securities', 'returns.center', 'imports.shipments',
        'production.orders', 'production.subcontracting', 'channels.accounts',
        'reports.center', 'reports.exports', 'settings.operations',
    ];
    foreach ($names as $name) {
        $route = Route::getRoutes()->getByName($name);
        expect($route)->not->toBeNull();
        expect($route->gatherMiddleware())->toContain('auth');
    }
});

it('v2 marketplace public hooks are protected by their dedicated throttle', function () {
    foreach (['webhooks.trendyol', 'webhooks.hepsiburada', 'webhooks.woocommerce'] as $name) {
        $route = Route::getRoutes()->getByName($name);
        expect($route)->not->toBeNull();
        expect($route->gatherMiddleware())->toContain('throttle:webhook');
    }
});

it('v2 channel asset route requires a Laravel signed URL', function () {
    $route = Route::getRoutes()->getByName('channels.asset');
    expect($route)->not->toBeNull();
    expect($route->gatherMiddleware())->toContain('signed');
});

it('v2 sensitive report UI rate-limits expensive queries', function () {
    $route = Route::getRoutes()->getByName('reports.center');
    expect($route)->not->toBeNull();
    expect($route->gatherMiddleware())->toContain('throttle:report');
});

it('v2 report export download has signed URL protection in addition to user ownership', function () {
    // Expected to expose a current contract gap: route currently has auth but no signed middleware.
    $route = Route::getRoutes()->getByName('reports.exports.download');
    expect($route)->not->toBeNull();
    expect($route->gatherMiddleware())->toContain('auth', 'signed');
});

it('v2 local health diagnostics cannot be called through the unauthenticated public network', function () {
    $route = Route::getRoutes()->getByName('health');
    expect($route)->not->toBeNull();
    expect($route->gatherMiddleware())->toContain('local.network');
});