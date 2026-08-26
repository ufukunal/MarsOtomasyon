<?php

namespace App\Modules\SalesInvoices\Actions;

final readonly class ResolvedSalesInvoiceLine
{
    public function __construct(
        public ?int $sourceSalesOrderId,
        public ?int $sourceSalesOrderLineId,
        public ?int $sourceDispatchId,
        public ?int $sourceDispatchLineId,
        public int $productId,
        public int $warehouseId,
        public int $locationId,
        public string $productCode,
        public string $productName,
        public ?string $description,
        public string $quantity,
    ) {}
}
