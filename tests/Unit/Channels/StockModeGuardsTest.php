<?php

use App\Models\Period\ChannelProductListing;
use App\Support\Channels\ChannelStockResolver;

it('requires lead time and fixed stock for production channel listings', function (): void {
    $resolver = new ChannelStockResolver;
    $method = new ReflectionMethod(ChannelStockResolver::class, 'production');
    expect(fn () => $method->invoke($resolver, new ChannelProductListing(['fixed_quantity' => '5'])))
        ->toThrow(DomainException::class);
    expect($method->invoke($resolver, new ChannelProductListing([
        'fixed_quantity' => '4', 'lead_time_days' => 2,
    ])))->toBe('4.000');
});

it('requires explicit stock for manual channel listings', function (): void {
    $resolver = new ChannelStockResolver;
    $method = new ReflectionMethod(ChannelStockResolver::class, 'manual');
    expect(fn () => $method->invoke($resolver, new ChannelProductListing))
        ->toThrow(DomainException::class);
    expect($method->invoke($resolver, new ChannelProductListing(['manual_quantity' => '2.7'])))->toBe('2.700');
});
