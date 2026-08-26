<?php

namespace App\Modules\SalesOrders\Actions;

use App\Modules\Quotes\Pricing\PriceBasis;

final readonly class SalesOrderLineData
{
    public function __construct(
        public int $productId,
        public string $quantity,
        public string $unitPrice,
        public PriceBasis $priceBasis,
        public string $lineDiscountRate,
        public ?int $taxZeroReasonId,
        public ?string $description,
        public ?string $logicalLineKey = null,
        public ?int $warehouseId = null,
        public ?int $locationId = null,
        public bool $taxIsZeroed = false,
    ) {}
}
