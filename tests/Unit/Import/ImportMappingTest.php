<?php

use App\Support\Import\ImportMapping;

it('defines required columns for all four import types', function (): void {
    foreach (['contact', 'product', 'price_list', 'opening_stock'] as $type) {
        $fields = ImportMapping::fields($type);
        expect($fields)->not->toBeEmpty();
        expect(collect($fields)->contains(fn (array $field): bool => $field['required']))->toBeTrue();
    }
    expect(ImportMapping::fields('unknown'))->toBe([]);
});

it('maps missing and unassigned columns to null, retaining zeros', function (): void {
    $mapped = ImportMapping::map(['sku' => 'ABC', 'count' => 0], [
        'product_code' => 'sku',
        'quantity' => 'count',
        'price' => 'missing',
        'note' => null,
    ]);
    expect($mapped)->toBe(['product_code' => 'ABC', 'quantity' => 0, 'price' => null, 'note' => null]);
});
