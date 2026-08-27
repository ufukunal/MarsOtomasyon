<?php

namespace App\Modules\PurchaseOrders\Enums;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Taslak',
        };
    }
}
