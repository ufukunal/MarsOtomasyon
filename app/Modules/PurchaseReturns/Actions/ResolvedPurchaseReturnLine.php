<?php

namespace App\Modules\PurchaseReturns\Actions;

use App\Modules\Quotes\Pricing\TaxCalculationLineResult;

final readonly class ResolvedPurchaseReturnLine
{
    public function __construct(
        public int $position,
        public int $purchaseOrderLineId,
        public int $goodsReceiptId,
        public int $goodsReceiptLineId,
        public int $supplierInvoiceId,
        public int $supplierInvoiceLineId,
        public int $productId,
        public int $warehouseId,
        public int $locationId,
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
