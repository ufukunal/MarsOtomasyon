<?php

namespace App\Modules\Inventory\Enums;

enum StockMovementType: string
{
    case OpeningIn = 'opening_in';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case DispatchOut = 'dispatch_out';

    public function label(): string
    {
        return match ($this) {
            self::OpeningIn => 'Açılış Girişi',
            self::AdjustmentIn => 'Düzeltme Girişi',
            self::AdjustmentOut => 'Düzeltme Çıkışı',
            self::TransferIn => 'Transfer Girişi',
            self::TransferOut => 'Transfer Çıkışı',
            self::DispatchOut => 'İrsaliye Çıkışı',
        };
    }

    public function isInbound(): bool
    {
        return match ($this) {
            self::OpeningIn, self::AdjustmentIn, self::TransferIn => true,
            self::AdjustmentOut, self::TransferOut, self::DispatchOut => false,
        };
    }
}
