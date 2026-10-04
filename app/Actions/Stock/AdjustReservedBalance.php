<?php

namespace App\Actions\Stock;

use App\Models\Period\StockBalance;
use DomainException;

final class AdjustReservedBalance
{
    public function handle(int $productId, int $locationId, string $delta): StockBalance
    {
        $balance = StockBalance::query()
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->lockForUpdate()
            ->firstOrFail();

        $next = bcadd((string) $balance->reserved, $delta, 3);

        if (bccomp($next, '0', 3) < 0) {
            throw new DomainException('Rezerve stok özeti negatif olamaz.');
        }

        $capacity = bcsub(
            bcsub((string) $balance->quantity, (string) $balance->consignment_reserved, 3),
            (string) $balance->quarantine,
            3,
        );

        if (bccomp($next, $capacity, 3) > 0) {
            throw new DomainException('Rezerve stok fiziksel kullanılabilir kapasiteyi aşamaz.');
        }

        $balance->setAttribute('reserved', $next);
        $balance->save();

        return $balance;
    }
}
