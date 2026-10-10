<?php

use App\Models\Period\ChannelProductListing;
use App\Models\Period\Product;
use App\Support\Channels\ChannelPriceResolver;

it('uses channel-specific price override before currency conversion or database reads', function (): void {
    $price = (new ReflectionClass(ChannelPriceResolver::class))->newInstanceWithoutConstructor();
    $listing = new ChannelProductListing(['price_override' => '12.50']);
    $listing->setRelation('product', new Product(['currency' => 'USD']));
    expect($price->price($listing))->toBe('12.5000');
});

it('blocks non-TRY channel pricing without explicit price override', function (): void {
    $price = (new ReflectionClass(ChannelPriceResolver::class))->newInstanceWithoutConstructor();
    $listing = new ChannelProductListing;
    $listing->setRelation('product', new Product(['currency' => 'EUR']));
    expect(fn () => $price->price($listing))->toThrow(DomainException::class);
});
