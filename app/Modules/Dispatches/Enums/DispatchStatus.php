<?php

namespace App\Modules\Dispatches\Enums;

enum DispatchStatus: string
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
