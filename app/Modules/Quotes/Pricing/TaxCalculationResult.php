<?php

namespace App\Modules\Quotes\Pricing;

final readonly class TaxCalculationResult
{
    /**
     * @param  list<TaxCalculationLineResult>  $lines
     */
    public function __construct(
        public array $lines,
        public string $baseNet,
        public string $lineDiscountNet,
        public string $documentDiscountNet,
        public string $net,
        public string $tax,
        public string $gross,
    ) {}
}
