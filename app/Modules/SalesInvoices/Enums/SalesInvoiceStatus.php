<?php

namespace App\Modules\SalesInvoices\Enums;

enum SalesInvoiceStatus: string
{
    case Draft = 'draft';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Taslak',
        };
    }
}
