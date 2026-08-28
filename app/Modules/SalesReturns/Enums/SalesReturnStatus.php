<?php

namespace App\Modules\SalesReturns\Enums;

enum SalesReturnStatus: string
{
    case Draft = 'draft';
    case Authorized = 'authorized';
    case Received = 'received';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Taslak',
            self::Authorized => 'Onaylandı',
            self::Received => 'Kabul Kontrolü Tamamlandı',
            self::Completed => 'Tamamlandı',
            self::Cancelled => 'İptal',
        };
    }
}
