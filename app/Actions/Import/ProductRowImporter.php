<?php

namespace App\Actions\Import;

use App\Actions\Products\SaveProduct;
use App\Models\Period\Product;
use App\Models\Period\Unit;
use App\Support\Import\RowValidationResult;

final class ProductRowImporter
{
    public function validate(array $row): RowValidationResult
    {
        $errors = [];
        $code = strtoupper(trim((string) ($row['code'] ?? '')));

        if ($code === '') {
            $errors['code'] = 'Ürün kodu zorunludur.';
        } elseif (Product::query()->where('code', $code)->exists()) {
            $errors['code'] = 'Ürün kodu hedefte zaten var.';
        }

        if (trim((string) ($row['name'] ?? '')) === '') {
            $errors['name'] = 'Ürün adı zorunludur.';
        }

        $unitCode = strtoupper(trim((string) ($row['unit_code'] ?? '')));

        if ($unitCode === '' || ! Unit::query()->where('code', $unitCode)->exists()) {
            $errors['unit_code'] = 'Birim kodu hedef period içinde bulunamadı.';
        }

        return new RowValidationResult($errors === [], $errors);
    }

    public function import(array $row): Product
    {
        $unitId = Unit::query()
            ->where('code', strtoupper(trim((string) $row['unit_code'])))
            ->value('id');

        return app(SaveProduct::class)->handle([
            'code' => $row['code'],
            'name' => $row['name'],
            'description' => null,
            'category_id' => null,
            'brand_id' => null,
            'unit_id' => $unitId,
            'barcode' => $row['barcode'] ?: null,
            'vat_rate' => $row['vat_rate'] ?: '20',
            'list_price' => $row['list_price'] ?: '0',
            'price_vat_included' => false,
            'currency' => $row['currency'] ?: 'TRY',
            'kind' => $row['kind'] ?: 'normal',
            'variant_group_id' => null,
            'allow_negative_stock' => false,
            'min_stock' => '0',
            'channel_stock_mode' => $row['channel_stock_mode'] ?: 'stock',
            'is_active' => true,
        ]);
    }
}
