<?php

namespace App\Modules\GoodsReceipts\Enums;

enum GoodsReceiptQualityDisposition: string
{
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Accepted => 'Kabul',
            self::Rejected => 'Red',
        };
    }
}
