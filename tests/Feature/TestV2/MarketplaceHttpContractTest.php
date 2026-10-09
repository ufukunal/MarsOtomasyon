<?php

use App\Models\SalesChannelAccount;
use App\Support\Channels\Hepsiburada\HepsiburadaClient;
use App\Support\Channels\N11\N11Client;
use App\Support\Channels\Trendyol\TrendyolClient;
use App\Support\Channels\WooCommerce\WooCommerceClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // No marketplace request is permitted to escape the fake transport.
    Http::preventStrayRequests();
});

it('v2 Trendyol GET uses staging host, pagination and Basic authentication', function () {
    Http::fake(['stageapigw.trendyol.com/*' => Http::response(['content' => [['id' => 37]]], 200)]);
    $account = new SalesChannelAccount([
        'credentials_encrypted' => ['api_key' => 'trendyol-test-key', 'api_secret' => 'trendyol-test-secret', 'seller_id' => 42],
        'settings' => ['environment' => 'stage', 'integrator_name' => 'Mars Test/QA'],
    ]);

    expect(app(TrendyolClient::class)->get($account, '/suppliers/42/orders', ['page' => 3]))
        ->toBe(['content' => [['id' => 37]]]);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && str_starts_with($request->url(), 'https://stageapigw.trendyol.com/suppliers/42/orders?')
        && str_contains($request->url(), 'page=3')
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('trendyol-test-key:trendyol-test-secret'))
        && $request->hasHeader('User-Agent', '42 - MarsTestQA')
    );
    Http::assertSentCount(1);
});

it('v2 Hepsiburada GET isolates service host and MerchantId authentication', function () {
    Http::fake(['oms-external-sit.hepsiburada.com/*' => Http::response(['orders' => [['id' => 8]]], 200)]);
    $account = new SalesChannelAccount([
        'credentials_encrypted' => ['username' => 'hb-test-user', 'service_key' => 'hb-test-key'],
        'external_store_id' => 'MERCHANT-3',
        'settings' => ['environment' => 'sit', 'integrator_name' => 'Mars Test/QA'],
    ]);

    $client = app(HepsiburadaClient::class);
    expect($client->merchantId($account))->toBe('MERCHANT-3')
        ->and($client->get($account, 'oms', '/orders', ['offset' => 20]))
        ->toBe(['orders' => [['id' => 8]]]);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && str_starts_with($request->url(), 'https://oms-external-sit.hepsiburada.com/orders?')
        && str_contains($request->url(), 'offset=20')
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('hb-test-user:hb-test-key'))
        && $request->hasHeader('User-Agent', 'MarsTestQA')
    );
    Http::assertSentCount(1);
});

it('v2 N11 GET passes account keys only in request headers and keeps pagination', function () {
    Http::fake(['api.n11.com/*' => Http::response(['data' => ['items' => [['id' => 5]]]], 200)]);
    $account = new SalesChannelAccount([
        'credentials_encrypted' => ['app_key' => 'n11-test-key', 'app_secret' => 'n11-test-secret'],
        'settings' => ['integrator_name' => 'MarsIntegration'],
    ]);

    expect(app(N11Client::class)->get($account, 'rest/delivery/v1/orders', ['page' => 2]))
        ->toBe(['data' => ['items' => [['id' => 5]]]]);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && str_starts_with($request->url(), 'https://api.n11.com/rest/delivery/v1/orders?')
        && str_contains($request->url(), 'page=2')
        && $request->hasHeader('appKey', 'n11-test-key')
        && $request->hasHeader('appSecret', 'n11-test-secret')
        && ! str_contains($request->url(), 'n11-test-secret')
    );
    Http::assertSentCount(1);
});

it('v2 WooCommerce GET builds the exact store REST path without leaking credentials', function () {
    Http::fake(['shop.example.com/*' => Http::response([['id' => 12]], 200)]);
    $account = new SalesChannelAccount([
        'credentials_encrypted' => ['consumer_key' => 'ck_test_001', 'consumer_secret' => 'cs_test_001'],
        'settings' => ['store_url' => 'https://shop.example.com/'],
    ]);

    expect(app(WooCommerceClient::class)->get($account, 'orders', ['page' => 2]))
        ->toBe([['id' => 12]]);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && str_starts_with($request->url(), 'https://shop.example.com/wp-json/wc/v3/orders?')
        && str_contains($request->url(), 'page=2')
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('ck_test_001:cs_test_001'))
        && ! str_contains($request->url(), 'cs_test_001')
    );
    Http::assertSentCount(1);
});

it('v2 all four marketplace clients reject missing credentials without making HTTP requests', function () {
    Http::fake();
    $empty = new SalesChannelAccount(['credentials_encrypted' => []]);

    foreach ([
        fn () => app(TrendyolClient::class)->get($empty, 'orders'),
        fn () => app(HepsiburadaClient::class)->get($empty, 'oms', 'orders'),
        fn () => app(N11Client::class)->get($empty, 'orders'),
        fn () => app(WooCommerceClient::class)->get($empty, 'orders'),
    ] as $call) {
        expect($call)->toThrow(DomainException::class);
    }

    Http::assertNothingSent();
});

it('v2 invalid Hepsiburada service rejects request before transport', function () {
    Http::fake();
    $account = new SalesChannelAccount([
        'credentials_encrypted' => ['username' => 'test-user', 'password' => 'test-password'],
        'external_store_id' => 'MERCHANT-3',
    ]);

    expect(fn () => app(HepsiburadaClient::class)->get($account, 'unknown-service', '/orders'))
        ->toThrow(DomainException::class, 'Geçersiz Hepsiburada servis adı.');
    Http::assertNothingSent();
});

it('v2 all four clients reject HTTP errors and never expose provider error bodies', function () {
    $testToken = 'SUPER-SECRET-FROM-REMOTE-BODY';
    Http::fake(['*' => Http::response('password='.$testToken, 429)]);

    $scenarios = [
        [TrendyolClient::class, new SalesChannelAccount([
            'credentials_encrypted' => ['api_key' => 'try-key', 'api_secret' => 'try-secret', 'seller_id' => 4],
        ]), fn ($client, $account) => $client->get($account, 'orders')],
        [HepsiburadaClient::class, new SalesChannelAccount([
            'credentials_encrypted' => ['username' => 'hb-user', 'password' => 'hb-pass'],
            'external_store_id' => 'H123',
        ]), fn ($client, $account) => $client->get($account, 'oms', 'orders')],
        [N11Client::class, new SalesChannelAccount([
            'credentials_encrypted' => ['app_key' => 'n11-key', 'app_secret' => 'n11-secret'],
        ]), fn ($client, $account) => $client->get($account, 'orders')],
        [WooCommerceClient::class, new SalesChannelAccount([
            'credentials_encrypted' => ['consumer_key' => 'woo-key', 'consumer_secret' => 'woo-secret'],
            'settings' => ['store_url' => 'https://shop.example.com'],
        ]), fn ($client, $account) => $client->get($account, 'orders')],
    ];

    foreach ($scenarios as [$class, $account, $call]) {
        try {
            $call(app($class), $account);
            test()->fail("{$class} should reject HTTP 429");
        } catch (DomainException $exception) {
            expect($exception->getMessage())->toContain('429')
                ->not->toContain($testToken)
                ->not->toContain('SUPER-SECRET');
        }
    }

    Http::assertSentCount(4);
});

it('v2 WooCommerce rejects localhost, private networks and credential-bearing URLs', function () {
    Http::fake();
    foreach ([
        'http://shop.example.com',
        'https://localhost',
        'https://127.0.0.1',
        'https://10.1.2.3',
        'https://192.168.1.10',
        'https://shop.internal',
        'https://shop.example.com?api_key=secret',
        '***shop.example.com',
        'https://shop.example.com/#fragment',
    ] as $url) {
        $account = new SalesChannelAccount(['settings' => ['store_url' => $url]]);
        expect(fn () => app(WooCommerceClient::class)->storeUrl($account))
            ->toThrow(DomainException::class);
    }

    Http::assertNothingSent();
});
