<?php

namespace App\Modules\SalesOrders\Enums;

enum SalesOrderProgressType: string
{
    case Dispatched = 'dispatched';
    case Invoiced = 'invoiced';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Dispatched => 'Sevk',
            self::Invoiced => 'Fatura',
            self::Cancelled => 'İptal',
        };
    }
}
