<?php

use App\Enums\SalesChannelPlatform;

it('keeps all four production channel identifiers stable', function (): void {
    expect(array_map(fn (SalesChannelPlatform $platform) => $platform->value, SalesChannelPlatform::cases()))
        ->toBe(['trendyol', 'hepsiburada', 'n11', 'woocommerce']);
    foreach (SalesChannelPlatform::cases() as $platform) {
        expect($platform->label())->not->toBeEmpty();
    }
});
