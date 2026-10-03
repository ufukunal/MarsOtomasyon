<?php

namespace App\Support\Pricing;

use App\Models\Period\Contact;
use App\Models\Period\PriceList;
use App\Models\Period\PriceListItem;
use App\Models\Period\Product;
use Carbon\CarbonInterface;

final class PriceResolver
{
    public function resolve(Product $product, ?Contact $contact = null, ?CarbonInterface $date = null): string
    {
        $date ??= now();

        $listId = $contact?->price_list_id
            ?? PriceList::query()->where('is_default', true)->where('is_active', true)->value('id');

        if ($listId) {
            $price = PriceListItem::query()
                ->where('price_list_id', $listId)
                ->where('product_id', $product->id)
                ->where(fn ($q) => $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', $date))
                ->where(fn ($q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date))
                ->orderByDesc('valid_from')
                ->value('price');

            if ($price !== null) {
                return bcadd((string) $price, '0', 4);
            }
        }

        return bcadd((string) ($product->list_price ?? '0'), '0', 4);
    }
}
