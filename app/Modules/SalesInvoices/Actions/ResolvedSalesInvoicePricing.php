<?php

namespace App\Modules\SalesInvoices\Actions;

use App\Modules\Quotes\Pricing\TaxCalculationResult;

final readonly class ResolvedSalesInvoicePricing
{
    /** @param list<ResolvedSalesInvoicePricingLine> $lines */
    public function __construct(
        public string $documentDiscountRate,
        public array $lines,
        public TaxCalculationResult $calculation,
    ) {}
}
