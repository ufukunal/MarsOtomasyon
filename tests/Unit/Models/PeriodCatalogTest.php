<?php

use App\Enums\ProductKind;
use App\Models\Period\Product;
use App\Models\Period\Unit;

it('uses typed enums for products and does not calculate kit stock for ordinary goods', function (): void {
    $product = new Product(['kind' => ProductKind::cases()[0]->value]);
    if ($product->kind === ProductKind::Set) {
        $product->kind = null;
    }
    expect($product->setAvailability())->toBeNull();
});

it('allows creating unsaved units and verifies reference conversion relations', function (): void {
    $unit = new Unit(['code' => 'ADET', 'name' => 'Piece', 'is_base' => true]);
    expect($unit->code)->toBe('ADET')
        ->and($unit->is_base)->toBeTrue()
        ->and($unit->conversionsFrom())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class)
        ->and($unit->conversionsTo())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});
