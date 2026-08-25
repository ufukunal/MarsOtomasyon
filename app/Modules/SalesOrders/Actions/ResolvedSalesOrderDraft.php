<?php

namespace App\Modules\SalesOrders\Actions;

use App\Modules\Quotes\Pricing\TaxCalculationResult;

final readonly class ResolvedSalesOrderDraft
{
    /** @param list<ResolvedSalesOrderLine> $lines */
    public function __construct(
        public int $accountId,
        public string $orderDate,
        public string $currencyCode,
        public string $documentDiscountRate,
        public ?string $note,
        public array $lines,
        public TaxCalculationResult $calculation,
    ) {}
}
