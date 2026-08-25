<?php

namespace App\Modules\Inventory\Enums;

enum ManualStockMovementKind: string
{
    case OpeningIn = 'opening_in';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';

    public function label(): string
    {
        return $this->ledgerType()->label();
    }

    public function isInbound(): bool
    {
        return $this->ledgerType()->isInbound();
    }

    public function ledgerType(): StockMovementType
    {
        return StockMovementType::from($this->value);
    }
}
