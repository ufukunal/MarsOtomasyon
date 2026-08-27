<?php

namespace App\Modules\PurchaseOrders\Actions;

use App\Modules\Quotes\Pricing\TaxCalculationLineResult;

final readonly class ResolvedPurchaseOrderLine
{
    public function __construct(
        public int $position,
        public string $logicalLineKey,
        public int $productId,
        public ?int $warehouseId,
        public ?int $locationId,
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
