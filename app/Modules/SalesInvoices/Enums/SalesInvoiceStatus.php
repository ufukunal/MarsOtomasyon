<?php

namespace App\Modules\SalesInvoices\Enums;

enum SalesInvoiceStatus: string
{
    case Draft = 'draft';
    case Finalized = 'finalized';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Taslak',
            self::Finalized => 'Kesinleşmiş',
            self::Cancelled => 'İptal',
        };
    }
}
