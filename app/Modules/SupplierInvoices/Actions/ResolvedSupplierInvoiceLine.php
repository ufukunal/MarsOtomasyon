<?php

namespace App\Modules\SupplierInvoices\Actions;

use App\Modules\Quotes\Pricing\TaxCalculationLineResult;

final readonly class ResolvedSupplierInvoiceLine
{
    public function __construct(
        public int $position,
        public int $purchaseOrderLineId,
        public int $productId,
        public string $productCode,
        public string $productName,
        public string $description,
        public int $taxId,
        public string $taxCode,
        public bool $taxIsZeroed,
        public ?int $taxZeroReasonId,
        public TaxCalculationLineResult $calculation,
    ) {}
}
