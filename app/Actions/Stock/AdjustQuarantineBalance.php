<?php

namespace App\Actions\Stock;

use App\Models\Period\StockBalance;
use DomainException;

final class AdjustQuarantineBalance
{
    public function handle(int $productId, int $locationId, string $delta): StockBalance
    {
        $balance = StockBalance::query()
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->lockForUpdate()
            ->firstOrFail();

        $next = bcadd((string) $balance->quarantine, $delta, 3);

        if (bccomp($next, '0', 3) < 0) {
            throw new DomainException('Karantina özeti negatif olamaz.');
        }

        if (bccomp($next, (string) $balance->quantity, 3) > 0) {
            throw new DomainException('Karantina özeti fiziksel stoğu aşamaz.');
        }

        $balance->setAttribute('quarantine', $next);
        $balance->save();

        return $balance;
    }
}
