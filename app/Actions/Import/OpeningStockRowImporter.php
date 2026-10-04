<?php

namespace App\Actions\Import;

use App\Actions\Stock\RecordStockMovement;
use App\DataObjects\StockMovementData;
use App\Models\Period\Location;
use App\Models\Period\Product;
use App\Support\Import\RowValidationResult;
use RuntimeException;

final class OpeningStockRowImporter
{
    /** @param array<string, mixed> $row */
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

        if (! class_exists('App\\Actions\\Stock\\ImportOpeningStock')) {
            $errors['stock'] = 'Faz 2 açılış stok akışı henüz tamamlanmadı.';
        }

        return new RowValidationResult($errors === [], $errors);
    }

    /** @param array<string, mixed> $row */
    public function import(array $row): void
    {
        if (! class_exists('App\\Actions\\Stock\\ImportOpeningStock')) {
            throw new RuntimeException('Açılış stok importu için Faz 2 açılış akışı gereklidir.');
        }

        $product = Product::query()->where('code', strtoupper(trim((string) $row['product_code'])))->firstOrFail();
        $location = Location::query()->where('code', strtoupper(trim((string) $row['location_code'])))->firstOrFail();

        app(RecordStockMovement::class)->handle(new StockMovementData(
            productId: $product->id,
            locationId: $location->id,
            movementDate: now()->toDateString(),
            direction: 'in',
            reason: 'opening',
            quantity: bcadd((string) $row['quantity'], '0', 3),
            unitCost: bcadd((string) $row['unit_cost'], '0', 4),
            updatesAverage: true,
        ));
    }
}
