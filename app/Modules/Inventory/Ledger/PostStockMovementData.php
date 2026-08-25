<?php

namespace App\Modules\Inventory\Ledger;

use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Inventory\Enums\StockMovementType;

final readonly class PostStockMovementData
{
    public function __construct(
        public SourceEffectIdentity $sourceEffect,
        public int $productId,
        public int $warehouseId,
        public int $locationId,
        public StockMovementType $movementType,
        public string $quantity,
        public ?string $unitCost = null,
        public ?string $note = null,
    ) {}
}
