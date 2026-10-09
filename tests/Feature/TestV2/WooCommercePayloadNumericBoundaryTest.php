<?php

use App\Support\Channels\WooCommerce\WooCommercePayloadBuilder;

it('v2 WooCommerce stock DTO is out of stock for nonpositive quantities', function (string $amount) {
    $result = app(WooCommercePayloadBuilder::class)->stock($amount);
    expect($result['stock_quantity'])->toBe(0)
        ->and($result['stock_status'])->toBe('outofstock')
        ->and($result['backorders'])->toBe('no');
})->with(['0.000', '-1.000']);

it('v2 WooCommerce stock DTO caps very large quantities at platform limit', function () {
    $result = app(WooCommercePayloadBuilder::class)->stock('9999999999.000');
    expect($result['stock_quantity'])->toBe(999999999);
});

it('v2 WooCommerce price DTO retains two-digit decimal string and clears stale sale price', function () {
    $result = app(WooCommercePayloadBuilder::class)->price('125.5000');
    expect($result)->toBe(['regular_price' => '125.50', 'sale_price' => '']);
});

it('v2 WooCommerce price DTO rejects negative prices', function () {
    expect(fn () => app(WooCommercePayloadBuilder::class)->price('-0.0001'))
        ->toThrow(DomainException::class);
});
