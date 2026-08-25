<?php

use App\Modules\Products\Pricing\ProductPriceNormalizer;
use InvalidArgumentException;

it('normalizes product net prices to exact six-decimal strings without binary float arithmetic', function (): void {
    $prices = new ProductPriceNormalizer;

    expect($prices->normalize(' 00125.5 '))->toBe('125.500000')
        ->and($prices->normalize('0'))->toBe('0.000000')
        ->and($prices->normalize('99999999999999.999999'))->toBe('99999999999999.999999');
});

it('rejects negative over-precision malformed and overflowing product prices', function (string $value): void {
    expect(fn (): string => (new ProductPriceNormalizer)->normalize($value))
        ->toThrow(InvalidArgumentException::class);
})->with([
    '-0.01',
    '1.0000001',
    '1,25',
    'abc',
    '100000000000000',
]);
