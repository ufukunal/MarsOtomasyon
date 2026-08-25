<?php

namespace App\Modules\Quotes\Enums;

enum QuoteStatus: string
{
    case Draft = 'draft';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Taslak',
            self::Cancelled => 'İptal',
        };
    }
}
