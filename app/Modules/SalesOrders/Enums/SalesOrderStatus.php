<?php

namespace App\Modules\SalesOrders\Enums;

enum SalesOrderStatus: string
{
    case Draft = 'draft';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Taslak',
        };
    }
}
