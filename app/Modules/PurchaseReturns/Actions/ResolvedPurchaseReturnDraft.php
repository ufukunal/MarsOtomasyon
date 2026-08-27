<?php

namespace App\Modules\PurchaseReturns\Actions;

use App\Modules\Quotes\Pricing\TaxCalculationResult;

final readonly class ResolvedPurchaseReturnDraft
{
    /** @param list<ResolvedPurchaseReturnLine> $lines */
    public function __construct(
        public int $purchaseOrderId,
        public int $accountId,
        public string $returnDate,
        public string $currencyCode,
        public string $documentDiscountRate,
        public ?string $note,
        public array $lines,
        public TaxCalculationResult $calculation,
    ) {}
}
