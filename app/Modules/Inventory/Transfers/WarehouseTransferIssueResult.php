<?php

namespace App\Modules\Inventory\Transfers;

use App\Modules\Inventory\Models\WarehouseTransfer;

final readonly class WarehouseTransferIssueResult
{
    public function __construct(
        public WarehouseTransfer $transfer,
        public bool $replayed,
    ) {}
}
