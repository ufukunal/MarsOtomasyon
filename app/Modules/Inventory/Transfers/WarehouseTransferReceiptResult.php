<?php

namespace App\Modules\Inventory\Transfers;

use App\Modules\Inventory\Models\WarehouseTransfer;
use App\Modules\Inventory\Models\WarehouseTransferReceipt;

final readonly class WarehouseTransferReceiptResult
{
    public function __construct(
        public WarehouseTransfer $transfer,
        public WarehouseTransferReceipt $receipt,
        public bool $replayed,
    ) {}
}
