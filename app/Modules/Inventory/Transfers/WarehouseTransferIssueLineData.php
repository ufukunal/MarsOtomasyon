<?php

namespace App\Modules\Inventory\Transfers;

final readonly class WarehouseTransferIssueLineData
{
    public function __construct(
        public int $productId,
        public string $quantity,
    ) {}
}
