<?php

namespace App\Modules\PurchaseOrders\Actions;

use App\Modules\Quotes\Pricing\TaxCalculationResult;

final readonly class ResolvedPurchaseOrderDraft
{
    /** @param list<ResolvedPurchaseOrderLine> $lines */
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
