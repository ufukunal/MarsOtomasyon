<?php

use App\Models\Period\Product;
use App\Support\Pricing\PriceDeviation;

it('does not emit warnings for zero reference price or deviations below twenty percent', function (): void {
    $product = new Product;
    $product->id = 123;
    $deviation = new PriceDeviation;
    expect($deviation->warnIfNeeded($product, '0', '100'))->toBeNull()
        ->and($deviation->warnIfNeeded($product, '-1', '100'))->toBeNull()
        ->and($deviation->warnIfNeeded($product, '100', '119.99'))->toBeNull()
        ->and($deviation->warnIfNeeded($product, '100', '80.01'))->toBeNull();
});
