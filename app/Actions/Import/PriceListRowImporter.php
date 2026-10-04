<?php

namespace App\Actions\Import;

use App\Actions\Pricing\SavePriceListItem;
use App\Models\Period\PriceList;
use App\Models\Period\Product;
use App\Support\Import\RowValidationResult;

final class PriceListRowImporter
{
    /** @param array<string, mixed> $row */
    public function validate(array $row): RowValidationResult
    {
        $errors = [];

        if (! PriceList::query()->where('name', trim((string) ($row['list_name'] ?? '')))->exists()) {
            $errors['list_name'] = 'Fiyat listesi bulunamadı.';
        }

        if (! Product::query()->where('code', strtoupper(trim((string) ($row['product_code'] ?? ''))))->exists()) {
            $errors['product_code'] = 'Ürün bulunamadı.';
        }

        if (! is_numeric((string) ($row['price'] ?? ''))) {
            $errors['price'] = 'Fiyat sayısal olmalıdır.';
        }

        return new RowValidationResult($errors === [], $errors);
    }

    /** @param array<string, mixed> $row */
    public function import(array $row): void
    {
        $list = PriceList::query()->where('name', trim((string) $row['list_name']))->firstOrFail();
        $product = Product::query()->where('code', strtoupper(trim((string) $row['product_code'])))->firstOrFail();

        app(SavePriceListItem::class)->handle(
            $list,
            $product,
            (string) $row['price'],
            $row['valid_from'] ?: null,
            $row['valid_to'] ?: null,
        );
    }
}
