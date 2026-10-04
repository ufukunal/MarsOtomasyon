<?php

namespace App\DataObjects\Purchases;

final readonly class PurchasePriceDeviation
{
    public function __construct(
        public string $previousPrice,
        public string $incomingPrice,
        public string $deviationRate,
        public bool $exceedsThreshold,
    ) {}
}
