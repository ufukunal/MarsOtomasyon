<?php

use App\Models\Period\ChannelProductListing;
use App\Models\Period\Product;
use App\Models\SalesChannelAccount;
use App\Support\Channels\ChannelContentResolver;
use App\Support\Channels\ChannelPriceResolver;
use App\Support\Channels\ChannelSensitiveDataRedactor;

it('v2 channel errors never include nested marketplace credentials', function () {
    $account = new SalesChannelAccount([
        'credentials_encrypted' => [
            'key' => 'ABCD-1234-secret',
            'nested' => ['password' => 'my-password-9876'],
        ],
    ]);

    $error = (new ChannelSensitiveDataRedactor)->redact(
        'HTTP authorization: Bearer ABCD-1234-secret password=my-password-9876',
        $account,
    );

    expect($error)->not->toContain('ABCD-1234-secret')
        ->not->toContain('my-password-9876')
        ->toContain('[REDACTED]');
});

it('v2 channel sensitive-data redactor hides marketplace HTTP response bodies', function () {
    $account = new SalesChannelAccount(['credentials_encrypted' => []]);

    $redacted = (new ChannelSensitiveDataRedactor)->redact(
        "Trendyol HTTP 401: token=merchant-secret\nmerchantPrivateData={...}",
        $account,
    );

    expect($redacted)->toBe('Trendyol HTTP 401 request failed.')
        ->not->toContain('merchant-secret')
        ->not->toContain('merchantPrivateData');
});

it('v2 channel errors are strictly limited to 2000 characters', function () {
    $account = new SalesChannelAccount(['credentials_encrypted' => []]);

    expect(mb_strlen((new ChannelSensitiveDataRedactor)->redact(str_repeat('x', 2500), $account)))
        ->toBe(2000);
});

it('v2 listing content falls back to product description and product title', function () {
    $product = new Product(['name' => '  Local Widget  ', 'description' => '']);
    $listing = new ChannelProductListing;
    $listing->setRelation('product', $product);

    expect((new ChannelContentResolver)->resolve($listing))
        ->toBe(['title' => 'Local Widget', 'description' => 'Local Widget']);
});

it('v2 listing content uses trimmed marketplace-specific overrides', function () {
    $product = new Product(['name' => 'Base', 'description' => 'Base Description']);
    $listing = new ChannelProductListing([
        'title_override' => '  New marketplace title  ',
        'description_override' => '  Marketplace description  ',
    ]);
    $listing->setRelation('product', $product);

    expect((new ChannelContentResolver)->resolve($listing))
        ->toBe(['title' => 'New marketplace title', 'description' => 'Marketplace description']);
});

it('v2 price override uses exact four-decimal currency representation', function () {
    $product = new Product(['name' => 'Global Item', 'currency' => 'USD']);
    $listing = new ChannelProductListing(['price_override' => '129.1234']);
    $listing->setRelation('product', $product);

    expect(app(ChannelPriceResolver::class)->price($listing))->toBe('129.1234');
});

it('v2 marketplace price requires explicit TRY override for non-TRY products', function () {
    $product = new Product(['name' => 'Global Item', 'currency' => 'USD']);
    $listing = new ChannelProductListing;
    $listing->setRelation('product', $product);

    expect(fn () => app(ChannelPriceResolver::class)->price($listing))
        ->toThrow(DomainException::class);
});
