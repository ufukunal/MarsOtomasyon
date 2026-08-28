<?php

namespace App\Modules\PurchaseOrders\Enums;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Taslak',
            self::Open => 'Açık',
            self::Closed => 'Kapalı',
        };
    }
}
