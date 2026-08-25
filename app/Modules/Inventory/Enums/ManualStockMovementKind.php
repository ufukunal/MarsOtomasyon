<?php

namespace App\Modules\Inventory\Enums;

enum ManualStockMovementKind: string
{
    case OpeningIn = 'opening_in';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';

    public function label(): string
    {
        return match ($this) {
            self::OpeningIn => 'Açılış Girişi',
            self::AdjustmentIn => 'Düzeltme Girişi',
            self::AdjustmentOut => 'Düzeltme Çıkışı',
        };
    }

    public function isInbound(): bool
    {
        return $this !== self::AdjustmentOut;
    }
}
