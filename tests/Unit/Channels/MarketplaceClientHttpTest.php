<?php

use App\Models\SalesChannelAccount;
use App\Support\Channels\ChannelSensitiveDataRedactor;
use App\Support\Channels\Hepsiburada\HepsiburadaClient;
use App\Support\Channels\N11\N11Client;
use App\Support\Channels\Trendyol\TrendyolClient;
use App\Support\Channels\WooCommerce\WooCommerceClient;
use Illuminate\Support\Facades\Http;

/** @param array<string,string> $credentials */
function marsHttpChannel(array $credentials = [], array $settings = [], ?string $store = null): SalesChannelAccount
{
    $account = new SalesChannelAccount;
    $account->forceFill([
        'credentials_encrypted' => $credentials,
        'settings' => $settings,
        'external_store_id' => $store,
    ]);

    return $account;
}

it('uses only mocked Trendyol staging HTTP requests with correct credentials and JSON decoding', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'stageapigw.trendyol.com/*' => Http::response(['content' => [['orderNumber' => 'T-11']]], 200),
    ]);
    $account = marsHttpChannel([
        'seller_id' => '12345',
        'api_key' => 'test-key',
        'api_secret' => 'test-secret',
    ], ['environment' => 'stage', 'integrator_name' => 'Mars Test']);

    $rows = (new TrendyolClient(new ChannelSensitiveDataRedactor))->get($account, '/integration/orders', ['page' => 1]);

    expect($rows['content'][0]['orderNumber'])->toBe('T-11');
    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://stageapigw.trendyol.com/integration/orders')
        && $request->hasHeader('User-Agent', '12345 - MarsTest')
        && $request->method() === 'GET');
    Http::assertSentCount(1);
});

it('blocks incomplete Trendyol credentials without sending requests', function (): void {
    Http::preventStrayRequests();
    Http::fake();

    $client = new TrendyolClient(new ChannelSensitiveDataRedactor);
    expect(fn () => $client->get(marsHttpChannel(['seller_id' => '77']), '/orders'))->toThrow(DomainException::class);
    expect(fn () => $client->sellerId(marsHttpChannel()))->toThrow(DomainException::class);
    Http::assertNothingSent();
});

it('handles Hepsiburada mock OMS failures without disclosing fake credentials', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'oms-external-sit.hepsiburada.com/*' => Http::response(['error' => 'invalid'], 503),
    ]);
    $account = marsHttpChannel([
        'username' => 'api-user',
        'service_key' => 'mock-service-secret',
        'merchant_id' => 'M-20',
    ], ['environment' => 'sit']);
    $client = new HepsiburadaClient(new ChannelSensitiveDataRedactor);

    expect(fn () => $client->get($account, 'oms', '/packages'))->toThrow(DomainException::class, 'HTTP 503');
    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://oms-external-sit.hepsiburada.com/packages'));
    Http::assertSentCount(1);
});

it('rejects unsupported Hepsiburada service names before dispatching HTTP', function (): void {
    Http::preventStrayRequests();
    Http::fake();
    $account = marsHttpChannel([
        'username' => 'api-user', 'password' => 'mock-pass', 'merchant_id' => 'M-2',
    ]);

    expect(fn () => (new HepsiburadaClient(new ChannelSensitiveDataRedactor))->get($account, 'unknown', '/data'))
        ->toThrow(DomainException::class);
    Http::assertNothingSent();
});

it('decodes N11 mock responses and redacts HTTP body secrets', function (): void {
    Http::preventStrayRequests();
    Http::fake(['api.n11.com/*' => Http::response(['items' => [1, 2]], 200)]);
    $account = marsHttpChannel(['app_key' => 'mock-app-key', 'app_secret' => 'mock-app-secret']);
    $client = new N11Client(new ChannelSensitiveDataRedactor);

    expect($client->get($account, '/orders')['items'])->toBe([1, 2]);
    Http::assertSent(fn ($request) => $request->hasHeader('appKey', 'mock-app-key')
        && $request->hasHeader('appSecret', 'mock-app-secret'));
});

it('handles WooCommerce mocked GET, PUT and POST without real network calls', function (): void {
    Http::preventStrayRequests();
    Http::fake(['example.com/wp-json/wc/v3/*' => Http::response(['id' => 9, 'status' => 'processing'])]);
    $account = marsHttpChannel(
        ['consumer_key' => 'ck_fake', 'consumer_secret' => 'cs_fake'],
        ['store_url' => 'https://example.com/'],
    );
    $client = new WooCommerceClient(new ChannelSensitiveDataRedactor);

    expect($client->get($account, 'orders/9')['id'])->toBe(9)
        ->and($client->put($account, 'orders/9', ['status' => 'completed'])['id'])->toBe(9)
        ->and($client->post($account, 'products', ['name' => 'Test'])['id'])->toBe(9);
    Http::assertSentCount(3);
});
