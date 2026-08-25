<?php

namespace App\Modules\Inventory\Ledger;

use App\Modules\Inventory\Models\StockMovement;

final readonly class StockMovementPostingResult
{
    public function __construct(
        public StockMovement $movement,
        public bool $replayed,
    ) {}
}
