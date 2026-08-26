<?php

namespace App\Modules\SalesInvoices\Actions;

use App\Modules\Quotes\Pricing\TaxCalculationLineResult;

final readonly class ResolvedSalesInvoicePricingLine
{
    public function __construct(
        public int $taxId,
        public string $taxCode,
        public bool $taxIsZeroed,
        public ?int $taxZeroReasonId,
        public TaxCalculationLineResult $calculation,
    ) {}
}
