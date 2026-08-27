<?php

namespace App\Modules\SupplierInvoices\Enums;

enum SupplierInvoiceStatus: string
{
    case Draft = 'draft';
    case Finalized = 'finalized';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Taslak',
            self::Finalized => 'Kesinleşti',
        };
    }
}
