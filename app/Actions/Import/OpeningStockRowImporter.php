<?php

namespace App\Actions\Import;

use App\Models\Period\Location;
use App\Models\Period\Product;
use App\Support\Import\RowValidationResult;
use RuntimeException;

final class OpeningStockRowImporter
{
    public function validate(array $row): RowValidationResult
    {
        $errors = [];

        if (! Product::query()->where('code', strtoupper(trim((string) ($row['product_code'] ?? ''))))->exists()) {
            $errors['product_code'] = 'Ürün bulunamadı.';
        }

        if (! Location::query()->where('code', strtoupper(trim((string) ($row['location_code'] ?? ''))))->exists()) {
            $errors['location_code'] = 'Lokasyon bulunamadı.';
        }

        if (bccomp((string) ($row['quantity'] ?? '0'), '0', 3) <= 0) {
            $errors['quantity'] = 'Açılış miktarı pozitif olmalıdır.';
        }

        if (bccomp((string) ($row['unit_cost'] ?? '0'), '0', 4) < 0) {
            $errors['unit_cost'] = 'Birim maliyet negatif olamaz.';
        }

        if (! class_exists('App\\Actions\\Stock\\RecordStockMovement')) {
            $errors['stock'] = 'Faz 2 stok hareket motoru henüz kurulmadı.';
        }

        return new RowValidationResult($errors === [], $errors);
    }

    public function import(array $row): void
    {
        if (! class_exists('App\\Actions\\Stock\\RecordStockMovement')) {
            throw new RuntimeException('Açılış stok importu için Faz 2 RecordStockMovement gereklidir.');
        }

        $product = Product::query()->where('code', strtoupper(trim((string) $row['product_code'])))->firstOrFail();
        $location = Location::query()->where('code', strtoupper(trim((string) $row['location_code'])))->firstOrFail();

        app('App\\Actions\\Stock\\RecordStockMovement')->handle([
            'product_id' => $product->id,
            'location_id' => $location->id,
            'movement_date' => now()->toDateString(),
            'direction' => 'in',
            'reason' => 'opening',
            'quantity' => bcadd((string) $row['quantity'], '0', 3),
            'unit_cost' => bcadd((string) $row['unit_cost'], '0', 4),
        ]);
    }
}
