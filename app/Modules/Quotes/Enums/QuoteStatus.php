<?php

namespace App\Modules\Quotes\Enums;

enum QuoteStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Converted = 'converted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Taslak',
            self::Approved => 'Onaylı',
            self::Rejected => 'Reddedildi',
            self::Converted => 'Siparişe Dönüştü',
            self::Cancelled => 'İptal',
        };
    }
}
