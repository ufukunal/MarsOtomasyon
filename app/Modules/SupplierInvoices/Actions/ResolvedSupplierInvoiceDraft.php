<?php

namespace App\Modules\SupplierInvoices\Actions;

use App\Modules\Quotes\Pricing\TaxCalculationResult;

final readonly class ResolvedSupplierInvoiceDraft
{
    /** @param list<ResolvedSupplierInvoiceLine> $lines */
    public function __construct(
        public int $purchaseOrderId,
        public int $accountId,
        public string $invoiceDate,
        public string $currencyCode,
        public string $documentDiscountRate,
        public ?string $note,
        public array $lines,
        public TaxCalculationResult $calculation,
    ) {}
}
