<?php

namespace App\Modules\SupplierInvoices\Enums;

/** Supplier invoice lifecycle states persisted by M9.4. */
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
