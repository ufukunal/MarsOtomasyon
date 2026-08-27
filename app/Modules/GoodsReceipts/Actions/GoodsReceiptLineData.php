<?php

namespace App\Modules\GoodsReceipts\Actions;

final readonly class GoodsReceiptLineData
{
    public function __construct(
        public int $purchaseOrderLineId,
        public int $warehouseId,
        public int $locationId,
        public string $receivedQuantity,
        public string $acceptedQuantity,
        public string $pendingQuantity,
        public string $rejectedQuantity,
        public ?string $note = null,
    ) {}
}
