<?php

namespace App\Actions\Products;

use App\Support\Auth\MutationAuthorizer;
use App\Enums\ProductKind;
use App\Models\Period\Product;
use App\Models\Period\ProductSet;
use Illuminate\Validation\ValidationException;

final class SaveSetComponent
{
    public function handle(
        Product $set,
        Product $component,
        string $quantity,
        ?ProductSet $line = null,
        ?int $expectedVersion = null,
    ): ProductSet {
        MutationAuthorizer::authorize('products.update');
        if ($set->kind !== ProductKind::Set) {
            throw ValidationException::withMessages(['set' => 'Bileşen yalnız set ürüne eklenebilir.']);
        }

        if ($component->kind === ProductKind::Set || $component->id === $set->id) {
            throw ValidationException::withMessages(['component' => 'Set ürün başka bir setin bileşeni olamaz.']);
        }

        if (bccomp($quantity, '0', 3) <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Bileşen miktarı pozitif olmalıdır.']);
        }

        $attributes = [
            'set_product_id' => $set->id,
            'component_product_id' => $component->id,
            'quantity' => bcadd($quantity, '0', 3),
        ];

        return $line
            ? $line->updateWithVersion($attributes, $expectedVersion ?? (int) $line->version)
            : ProductSet::query()->create($attributes);
    }
}
