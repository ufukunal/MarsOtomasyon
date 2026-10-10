<?php

use App\Models\SalesChannelAccount;
use App\Support\Channels\ChannelSensitiveDataRedactor;
use App\Support\Channels\WooCommerce\WooCommerceClient;
use Illuminate\Support\Facades\Http;

it('refuses localhost, internal networks and embedded credentials as WooCommerce endpoints', function (string $url): void {
    Http::preventStrayRequests();
    Http::fake();
    $account = new SalesChannelAccount;
    $account->forceFill(['settings' => ['store_url' => $url]]);
    $client = new WooCommerceClient(new ChannelSensitiveDataRedactor);

    expect(fn () => $client->storeUrl($account))->toThrow(DomainException::class);
    Http::assertNothingSent();
})->with([
    'http scheme' => ['http://example.com'],
    'localhost' => ['https://localhost'],
    'loopback' => ['https://127.0.0.1'],
    'private range' => ['https://192.168.1.100'],
    'link local' => ['https://169.254.1.1'],
    'private dns' => ['https://service.internal'],
    'local dns' => ['https://shop.local'],
    'userinfo' => ['https://user:secret@example.com'],
    'query' => ['https://example.com?token=secret'],
    'fragment' => ['https://example.com/#credentials'],
    'empty' => [''],
]);

it('normalizes the trailing slash but preserves a public HTTPS store root', function (): void {
    $account = new SalesChannelAccount;
    $account->forceFill(['settings' => ['store_url' => 'https://example.com/']]);
    $client = new WooCommerceClient(new ChannelSensitiveDataRedactor);
    expect($client->storeUrl($account))->toBe('https://example.com');
});
