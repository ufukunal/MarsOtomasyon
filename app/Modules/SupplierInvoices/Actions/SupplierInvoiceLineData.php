<?php

namespace App\Modules\SupplierInvoices\Actions;

final readonly class SupplierInvoiceLineData
{
    public function __construct(
        public int $purchaseOrderLineId,
        public string $quantity,
    ) {}
}
