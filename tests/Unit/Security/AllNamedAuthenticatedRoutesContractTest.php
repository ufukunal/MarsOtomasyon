<?php

use Illuminate\Support\Facades\Route;

it('requires authentication on every named company or period business screen', function (string $name): void {
    $route = Route::getRoutes()->getByName($name);

    expect($route)->not->toBeNull()
        ->and($route->methods())->toContain('GET')
        ->and($route->gatherMiddleware())->toContain('auth');
})->with([
    'home',
    'settings.periods',
    'settings.period-carry',
    'locations.index',
    'units.index',
    'categories.index',
    'brands.index',
    'stock.status',
    'sales.orders.index',
    'purchases.orders.index',
    'finance.operations',
    'reports.center',
    'returns.center',
    'channels.accounts',
    'production.orders',
]);

it('places rate limits and no-session guards on all named public channel webhooks', function (string $routeName, string $method): void {
    $route = Route::getRoutes()->getByName($routeName);

    expect($route)->not->toBeNull()
        ->and($route->methods())->toContain($method)
        ->and($route->gatherMiddleware())->toContain('throttle:webhook');
})->with([
    ['webhooks.trendyol', 'POST'],
    ['webhooks.hepsiburada', 'PUT'],
    ['webhooks.woocommerce', 'POST'],
]);
