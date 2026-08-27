<?php

namespace App\Modules\PurchaseReturns\Actions;

final readonly class PurchaseReturnLineData
{
    public function __construct(
        public int $goodsReceiptLineId,
        public int $supplierInvoiceLineId,
        public string $quantity,
    ) {}
}
