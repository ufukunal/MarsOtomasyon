<?php

namespace App\Modules\SalesInvoices\Enums;

enum SalesInvoiceEDocumentEventType: string
{
    case Prepared = 'prepared';
    case Submitted = 'submitted';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Prepared => 'Hazırlandı',
            self::Submitted => 'Gönderildi',
            self::Accepted => 'Kabul Edildi',
            self::Rejected => 'Reddedildi',
            self::Cancelled => 'İptal Edildi',
        };
    }
}
