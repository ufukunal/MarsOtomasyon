<?php

namespace App\Modules\GoodsReceipts\Actions;

final readonly class ResolvedGoodsReceiptLine
{
    public function __construct(
        public int $purchaseOrderLineId,
        public int $productId,
        public int $warehouseId,
        public int $locationId,
        public string $productCode,
        public string $productName,
        public string $receivedQuantity,
        public string $acceptedQuantity,
        public string $pendingQuantity,
        public string $rejectedQuantity,
        public string $provisionalUnitCost,
        public ?string $note,
    ) {}
}
