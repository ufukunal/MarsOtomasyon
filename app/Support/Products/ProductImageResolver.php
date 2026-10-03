<?php

namespace App\Support\Products;

use App\Models\Attachment;
use App\Models\Period\Product;
use Illuminate\Database\Eloquent\Collection;

final class ProductImageResolver
{
    /**
     * @return Collection<int, Attachment>
     */
    public function forCollection(Product $product, string $collection): Collection
    {
        $images = $product->attachments()
            ->where('collection', $collection)
            ->orderBy('sort_order')
            ->get();

        if ($images->isNotEmpty() || $collection === config('product_images.fallback')) {
            return $images;
        }

        return $product->attachments()
            ->where('collection', config('product_images.fallback'))
            ->orderBy('sort_order')
            ->get();
    }
}
