<?php

namespace App\Modules\Core\Enums;

enum PostingPeriodStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Açık',
            self::Closed => 'Kapalı',
        };
    }
}
