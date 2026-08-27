<?php

namespace App\Modules\SalesInvoices\Enums;

enum SalesInvoiceEDocumentType: string
{
    case EInvoice = 'e_invoice';
    case EArchive = 'e_archive';

    public function label(): string
    {
        return match ($this) {
            self::EInvoice => 'E-Fatura',
            self::EArchive => 'E-Arşiv',
        };
    }
}
