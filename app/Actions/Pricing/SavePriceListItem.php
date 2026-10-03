<?php

namespace App\Actions\Pricing;

use App\Support\Period\PeriodContext;
use App\Support\Auth\MutationAuthorizer;
use App\Models\Period\PriceList;
use App\Models\Period\PriceListItem;
use App\Models\Period\Product;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

final class SavePriceListItem
{
    public function handle(
        PriceList $list,
        Product $product,
        string $inputPrice,
        ?string $validFrom = null,
        ?string $validTo = null,
        ?PriceListItem $item = null,
        ?int $expectedVersion = null,
    ): PriceListItem {
        MutationAuthorizer::authorize('price_lists.update');
        PeriodContext::ensureWritable();
        $price = bcadd($inputPrice, '0', 4);

        if ($list->vat_included) {
            $factor = bcadd('1', bcdiv((string) $product->vat_rate, '100', 8), 8);
            $price = bcdiv($price, $factor, 4);
        }

        $overlap = PriceListItem::query()
            ->where('price_list_id', $list->id)
            ->where('product_id', $product->id)
            ->when($item, fn ($q) => $q->whereKeyNot($item->id))
            ->where(function ($q) use ($validFrom, $validTo): void {
                $from = $validFrom ?: '0001-01-01';
                $to = $validTo ?: '9999-12-31';

                $q->whereRaw(
                    "daterange(COALESCE(valid_from, '0001-01-01'::date), COALESCE(valid_to, '9999-12-31'::date), '[]') && daterange(?::date, ?::date, '[]')",
                    [$from, $to],
                );
            })
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'date' => 'Aynı ürün için fiyat tarih aralıkları çakışamaz.',
            ]);
        }

        $attributes = [
            'price_list_id' => $list->id,
            'product_id' => $product->id,
            'price' => $price,
            'valid_from' => $validFrom ?: null,
            'valid_to' => $validTo ?: null,
        ];

        try {
            return $item
                ? $item->updateWithVersion($attributes, $expectedVersion ?? (int) $item->version)
                : PriceListItem::query()->create($attributes);
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23P01') {
                throw ValidationException::withMessages([
                    'date' => 'Aynı ürün için fiyat tarih aralıkları çakışamaz.',
                ]);
            }

            throw $exception;
        }
    }
}
