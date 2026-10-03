<?php

namespace App\Support\Products;

use App\Models\Period\Product;

final class SetAvailabilityCalculator
{
    public function forProduct(Product $set): string
    {
        $components = $set->setComponents()->with('componentProduct')->get();

        if ($components->isEmpty()) {
            return '0';
        }

        $minimum = null;

        foreach ($components as $component) {
            $stock = $component->componentProduct->availableQuantity();
            $required = (string) $component->quantity;

            if (bccomp($required, '0', 3) <= 0) {
                return '0';
            }

            $count = bcdiv($stock, $required, 0);

            $minimum = $minimum === null || bccomp($count, $minimum, 0) < 0
                ? $count
                : $minimum;
        }

        return $minimum ?? '0';
    }
}
