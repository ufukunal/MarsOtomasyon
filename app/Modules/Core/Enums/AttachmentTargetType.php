<?php

namespace App\Modules\Core\Enums;

enum AttachmentTargetType: string
{
    case Company = 'company';

    public function label(): string
    {
        return match ($this) {
            self::Company => 'Firma',
        };
    }
}
