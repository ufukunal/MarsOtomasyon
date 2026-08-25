<?php

namespace App\Modules\Quotes\Actions;

use App\Modules\Quotes\Pricing\PriceBasis;

final readonly class QuoteLineData
{
    public function __construct(
        public int $productId,
        public string $quantity,
        public string $unitPrice,
        public PriceBasis $priceBasis,
        public string $lineDiscountRate = '0',
        public ?int $taxZeroReasonId = null,
        public ?string $description = null,
    ) {}
}
