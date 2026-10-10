<?php

use Illuminate\Support\Facades\Route;

it('requires auth for every module UI screen, including exports and downloads', function (): void {
    $prefixes = [
        'sales.', 'purchases.', 'finance.', 'returns.', 'imports.', 'production.',
        'channels.', 'reports.', 'stock.', 'settings.', 'products.', 'contacts.',
        'locations.', 'units.', 'categories.', 'brands.', 'price-lists.',
        'variant-groups.', 'company-copy.',
    ];
    $matched = [];
    foreach (Route::getRoutes() as $route) {
        $name = (string) $route->getName();
        if (! array_any($prefixes, fn (string $prefix): bool => str_starts_with($name, $prefix))) {
            continue;
        }
        $matched[] = $name;
        expect($route->gatherMiddleware())->toContain('auth');
    }
    expect($matched)->not->toBeEmpty();
});

it('keeps webhooks throttled and public channel assets signed', function (): void {
    foreach (['webhooks.trendyol', 'webhooks.hepsiburada', 'webhooks.woocommerce'] as $name) {
        $route = Route::getRoutes()->getByName($name);
        expect($route)->not->toBeNull()
            ->and($route->gatherMiddleware())->toContain('throttle:webhook');
    }
    expect(Route::getRoutes()->getByName('channels.asset')->gatherMiddleware())->toContain('signed');
});

it('keeps local health endpoint off the public network and logout POST-only', function (): void {
    $health = Route::getRoutes()->getByName('health');
    expect($health->gatherMiddleware())->toContain('local.network');
    $logout = Route::getRoutes()->getByName('logout');
    expect($logout->methods())->toContain('POST')
        ->and($logout->gatherMiddleware())->toContain('auth');
});

it('registers the expected screen and integration module entry points', function (string $name): void {
    expect(Route::getRoutes()->getByName($name))->not->toBeNull();
})->with([
    'home', 'login', 'period.select', 'stock.status', 'stock.counts.index',
    'stock.transfers.index', 'stock.warehouse-slips.index', 'stock.quarantine.index',
    'products.index', 'units.index', 'contacts.index', 'price-lists.index',
    'sales.quotes.index', 'sales.orders.index', 'sales.invoices.index',
    'purchases.orders.index', 'purchases.receipts.index', 'purchases.invoices.index',
    'finance.operations', 'finance.bank-statements', 'returns.center', 'imports.shipments',
    'production.recipes', 'production.orders', 'production.subcontracting',
    'channels.accounts', 'channels.listings', 'channels.sync',
    'reports.center', 'reports.consolidated', 'reports.exports',
    'settings.periods', 'settings.integrity', 'settings.operations',
]);
