<?php

namespace App\Modules\Quotes\Pricing;

final readonly class TaxCalculationLineResult
{
    public function __construct(
        public string $key,
        public string $quantity,
        public string $unitPrice,
        public PriceBasis $priceBasis,
        public string $taxRate,
        public string $lineDiscountRate,
        public string $documentDiscountRate,
        public ?string $taxZeroReasonCode,
        public string $baseNet,
        public string $lineDiscountNet,
        public string $documentDiscountNet,
        public string $net,
        public string $tax,
        public string $gross,
    ) {}
}
