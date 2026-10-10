<?php

use App\Support\Channels\WooCommerce\WooCommercePayloadBuilder;

it('marks zero and negative channel inventory unavailable and disables backorders', function (string $quantity, int $expected, string $status): void {
    $builder = (new ReflectionClass(WooCommercePayloadBuilder::class))->newInstanceWithoutConstructor();
    $data = $builder->stock($quantity);
    expect($data['stock_quantity'])->toBe($expected)
        ->and($data['stock_status'])->toBe($status)
        ->and($data['backorders'])->toBe('no')
        ->and($data['manage_stock'])->toBeTrue();
})->with([
    ['0', 0, 'outofstock'],
    ['-5', 0, 'outofstock'],
    ['1.999', 1, 'instock'],
    ['100', 100, 'instock'],
    ['999999999999', 999999999, 'instock'],
]);

it('uses fixed-decimal prices and rejects negative listing prices', function (): void {
    $builder = (new ReflectionClass(WooCommercePayloadBuilder::class))->newInstanceWithoutConstructor();
    expect($builder->price('12.34'))->toBe(['regular_price' => '12.34', 'sale_price' => '']);
    expect(fn () => $builder->price('-0.0001'))->toThrow(DomainException::class);
});
