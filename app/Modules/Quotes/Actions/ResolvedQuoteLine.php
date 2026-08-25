<?php

namespace App\Modules\Quotes\Actions;

use App\Modules\Quotes\Pricing\TaxCalculationLineResult;

final readonly class ResolvedQuoteLine
{
    public function __construct(
        public int $position,
        public int $productId,
        public string $productCode,
        public string $description,
        public int $taxId,
        public ?int $taxZeroReasonId,
        public TaxCalculationLineResult $calculation,
    ) {}
}
