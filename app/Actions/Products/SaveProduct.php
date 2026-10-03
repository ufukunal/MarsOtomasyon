<?php

namespace App\Actions\Products;

use App\Support\Auth\MutationAuthorizer;
use App\Models\Period\Product;
use App\Support\Period\PeriodContext;
use Illuminate\Validation\ValidationException;

final class SaveProduct
{
    public function handle(
        array $data,
        ?Product $product = null,
        ?int $expectedVersion = null,
    ): Product {
        MutationAuthorizer::authorize($product ? 'products.update' : 'products.create');
        PeriodContext::ensureWritable();

        $vatRate = bcadd((string) ($data['vat_rate'] ?? '0'), '0', 4);
        $inputPrice = bcadd((string) ($data['list_price'] ?? '0'), '0', 4);

        if ((bool) ($data['price_vat_included'] ?? false)) {
            $vatFactor = bcadd('1', bcdiv($vatRate, '100', 8), 8);

            if (bccomp($vatFactor, '0', 8) <= 0) {
                throw ValidationException::withMessages(['vat_rate' => 'KDV oranı geçersiz.']);
            }

            $inputPrice = bcdiv($inputPrice, $vatFactor, 4);
        }

        $attributes = [
            'code' => strtoupper(trim((string) $data['code'])),
            'name' => trim((string) $data['name']),
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'category_id' => $data['category_id'] ?: null,
            'brand_id' => $data['brand_id'] ?: null,
            'unit_id' => (int) $data['unit_id'],
            'barcode' => trim((string) ($data['barcode'] ?? '')) ?: null,
            'vat_rate' => $vatRate,
            'list_price' => $inputPrice,
            'currency' => strtoupper((string) ($data['currency'] ?? 'TRY')),
            'kind' => (string) ($data['kind'] ?? 'normal'),
            'variant_group_id' => $data['variant_group_id'] ?: null,
            'allow_negative_stock' => (bool) ($data['allow_negative_stock'] ?? false),
            'min_stock' => bcadd((string) ($data['min_stock'] ?? '0'), '0', 3),
            'channel_stock_mode' => (string) ($data['channel_stock_mode'] ?? 'stock'),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        if (! $product) {
            return Product::query()->create($attributes);
        }

        unset($attributes['code']);

        return $product->updateWithVersion(
            $attributes,
            $expectedVersion ?? (int) $product->version,
        );
    }
}
