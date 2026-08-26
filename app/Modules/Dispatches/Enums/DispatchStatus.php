<?php

namespace App\Modules\Dispatches\Enums;

enum DispatchStatus: string
{
    case Draft = 'draft';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Taslak',
        };
    }
}
