<?php

namespace App\Modules\SalesInvoices\Actions;

use App\Modules\Quotes\Pricing\PriceBasis;

final readonly class SalesInvoiceLineData
{
    public function __construct(
        public string $quantity,
        public ?int $productId = null,
        public ?int $salesOrderLineId = null,
        public ?int $dispatchLineId = null,
        public ?int $warehouseId = null,
        public ?int $locationId = null,
        public ?string $unitPrice = null,
        public ?PriceBasis $priceBasis = null,
        public ?string $lineDiscountRate = null,
        public bool $taxIsZeroed = false,
        public ?int $taxZeroReasonId = null,
    ) {}
}
