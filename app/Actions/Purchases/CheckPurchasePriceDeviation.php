<?php

namespace App\Actions\Purchases;

use App\DataObjects\Purchases\PurchasePriceDeviation;
use App\Models\Period\ProductCost;

final class CheckPurchasePriceDeviation
{
    private const THRESHOLD = '25.0000';

    public function handle(int $productId, string $incomingPrice): PurchasePriceDeviation
    {
        $incoming = bcadd($incomingPrice, '0', 4);
        $previous = ProductCost::query()
            ->where('product_id', $productId)
            ->value('last_purchase_price');
        $previousPrice = bcadd((string) ($previous ?? '0'), '0', 4);

        if (bccomp($previousPrice, '0', 4) <= 0) {
            return new PurchasePriceDeviation(
                previousPrice: $previousPrice,
                incomingPrice: $incoming,
                deviationRate: '0.0000',
                exceedsThreshold: false,
            );
        }

        $difference = bcsub($incoming, $previousPrice, 8);
        $rate = bcdiv(
            bcmul($difference, '100', 8),
            $previousPrice,
            4,
        );
        $absolute = str_starts_with($rate, '-') ? substr($rate, 1) : $rate;

        return new PurchasePriceDeviation(
            previousPrice: $previousPrice,
            incomingPrice: $incoming,
            deviationRate: $rate,
            exceedsThreshold: bccomp($absolute, self::THRESHOLD, 4) > 0,
        );
    }
}
