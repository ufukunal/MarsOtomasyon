<?php

namespace App\Actions\Import;

use App\DataObjects\OpeningStockRowData;
use App\Models\Period\Location;
use App\Models\Period\Product;
use App\Support\Import\RowValidationResult;
use LogicException;

final class OpeningStockRowImporter
{
    /** @param array<string, mixed> $row */
    public function validate(array $row): RowValidationResult
    {
        $errors = [];
        $data = OpeningStockRowData::fromArray($row);

        if ($data->productCode === '' || ! Product::query()->where('code', $data->productCode)->exists()) {
            $errors['product_code'] = 'Ürün bulunamadı.';
        }

        if ($data->locationCode === '' || ! Location::query()->where('code', $data->locationCode)->exists()) {
            $errors['location_code'] = 'Lokasyon bulunamadı.';
        }

        if ($data->quantity === '' || ! is_numeric($data->quantity) || bccomp($data->quantity, '0', 3) <= 0) {
            $errors['quantity'] = 'Açılış miktarı pozitif sayısal olmalıdır.';
        }

        if ($data->unitCost === '') {
            $errors['unit_cost'] = 'Birim maliyet zorunludur.';
        } elseif (! is_numeric($data->unitCost) || bccomp($data->unitCost, '0', 4) < 0) {
            $errors['unit_cost'] = 'Birim maliyet sıfırdan büyük veya eşit sayısal olmalıdır.';
        }

        return new RowValidationResult($errors === [], $errors);
    }

    /** @param array<string, mixed> $row */
    public function import(array $row): void
    {
        throw new LogicException('Açılış stok satırları yalnız ImportOpeningStock batch action ile uygulanır.');
    }
}
