<?php

namespace App\Actions\Products;

use App\Support\Auth\MutationAuthorizer;
use App\Models\Period\Product;
use App\Models\Period\ProductVariantValue;
use App\Models\Period\VariantAttribute;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveVariantValues
{
    public function handle(Product $product, int $groupId, array $values): array
    {
        MutationAuthorizer::authorize('products.update');
        if ((int) $product->variant_group_id !== $groupId) {
            $product = $product->updateWithVersion(
                ['variant_group_id' => $groupId],
                (int) $product->version,
            );
        }

        $attributes = VariantAttribute::query()
            ->where('variant_group_id', $groupId)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $unknown = array_diff(array_map('intval', array_keys($values)), $attributes);

        if ($unknown !== []) {
            throw ValidationException::withMessages(['values' => 'Varyant özelliği seçilen gruba ait değil.']);
        }

        return DB::connection('period')->transaction(function () use ($product, $values): array {
            foreach ($values as $attributeId => $value) {
                $existing = ProductVariantValue::query()
                    ->where('product_id', $product->id)
                    ->where('variant_attribute_id', (int) $attributeId)
                    ->first();

                if ($existing) {
                    $existing->updateWithVersion(
                        ['value' => trim((string) $value)],
                        (int) $existing->version,
                    );
                } else {
                    ProductVariantValue::query()->create([
                        'product_id' => $product->id,
                        'variant_attribute_id' => (int) $attributeId,
                        'value' => trim((string) $value),
                    ]);
                }
            }

            return $this->duplicateCombinationWarnings($product);
        });
    }

    private function duplicateCombinationWarnings(Product $product): array
    {
        $current = $product->variantValues()
            ->orderBy('variant_attribute_id')
            ->pluck('value', 'variant_attribute_id')
            ->all();

        if ($current === []) {
            return [];
        }

        $others = Product::query()
            ->where('variant_group_id', $product->variant_group_id)
            ->whereKeyNot($product->id)
            ->with('variantValues')
            ->get();

        foreach ($others as $other) {
            $candidate = $other->variantValues
                ->sortBy('variant_attribute_id')
                ->pluck('value', 'variant_attribute_id')
                ->all();

            if ($candidate === $current) {
                return ["{$other->code} aynı varyant kombinasyonunu kullanıyor."];
            }
        }

        return [];
    }
}
