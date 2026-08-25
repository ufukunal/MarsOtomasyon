<?php

namespace App\Modules\Quotes\Pricing;

final readonly class TaxCalculationLineInput
{
    public function __construct(
        public string $key,
        public string $quantity,
        public string $unitPrice,
        public PriceBasis $priceBasis,
        public string $taxRate,
        public string $lineDiscountRate = '0',
        public ?string $taxZeroReasonCode = null,
    ) {}
}
