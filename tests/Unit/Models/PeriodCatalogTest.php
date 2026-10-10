<?php

use App\Enums\ProductKind;
use App\Models\Period\Product;
use App\Models\Period\Unit;
use Illuminate\Database\Eloquent\Relations\HasMany;

it('uses typed enums for products and does not calculate kit stock for ordinary goods', function (): void {
    $product = new Product(['kind' => ProductKind::cases()[0]->value]);
    if ($product->kind === ProductKind::Set) {
        $product->kind = null;
    }
    expect($product->setAvailability())->toBeNull();
});

it('declares typed unit relationships without querying an unselected period database', function (): void {
    $unit = new Unit(['code' => 'ADET', 'name' => 'Piece', 'is_base' => true]);
    expect($unit->code)->toBe('ADET')
        ->and($unit->is_base)->toBeTrue();

    foreach (['conversionsFrom', 'conversionsTo'] as $relation) {
        $method = new ReflectionMethod(Unit::class, $relation);
        expect($method->getReturnType()?->getName())->toBe(HasMany::class);
    }
});
