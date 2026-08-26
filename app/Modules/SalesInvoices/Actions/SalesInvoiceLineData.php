<?php

namespace App\Modules\SalesInvoices\Actions;

final readonly class SalesInvoiceLineData
{
    public function __construct(
        public string $quantity,
        public ?int $productId = null,
        public ?int $salesOrderLineId = null,
        public ?int $dispatchLineId = null,
        public ?int $warehouseId = null,
        public ?int $locationId = null,
    ) {}
}
