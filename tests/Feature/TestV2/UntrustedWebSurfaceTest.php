<?php

use Illuminate\Support\Facades\Route;

it('v2 sensitive page families require authentication in actual route metadata', function () {
    foreach ([
        'home',
        'sales.orders.index',
        'purchases.orders.index',
        'returns.center',
        'finance.accounts',
        'imports.shipments',
        'production.orders',
        'production.subcontracting',
        'channels.accounts',
        'channels.sync',
        'reports.center',
        'reports.exports',
        'reports.templates',
    ] as $name) {
        $route = Route::getRoutes()->getByName($name);

        expect($route)->not->toBeNull()
            ->and($route->gatherMiddleware())->toContain('auth');
    }
});

it('v2 anonymous user cannot access sensitive business pages', function () {
    foreach ([
        '/satis/siparisler',
        '/alis/siparisler',
        '/iadeler',
        '/finans/hesaplar',
        '/ithalat',
        '/uretim/emirler',
        '/e-ticaret/kanal-hesaplari',
    ] as $path) {
        $this->get($path)->assertRedirect(route('login'));
    }
});

it('v2 guest JSON requests receive 401 instead of a masked server error', function () {
    $this->getJson('/satis/siparisler')->assertUnauthorized();
});

it('v2 public channel asset endpoint rejects unsigned URLs', function () {
    $this->get('/channel-assets/1/1/1/1')->assertForbidden();
});

it('v2 anonymous requests cannot produce marketplace side effects for missing accounts', function () {
    $this->postJson('/hooks/channel/999999999', ['status' => 'CREATED', 'orderNumber' => 'TEST'])
        ->assertNotFound();

    $this->putJson('/hooks/channel/hepsiburada/999999999/createOrder', [])
        ->assertNotFound();

    $this->postJson('/hooks/channel/woocommerce/999999999', ['id' => 123])
        ->assertNotFound();
});
