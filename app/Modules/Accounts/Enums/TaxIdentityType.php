<?php

namespace App\Modules\Accounts\Enums;

enum TaxIdentityType: string
{
    case None = 'none';
    case Vkn = 'vkn';
    case Tckn = 'tckn';
    case Foreign = 'foreign';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Vergi kimliği yok',
            self::Vkn => 'VKN',
            self::Tckn => 'TCKN',
            self::Foreign => 'Yabancı Kimlik',
        };
    }
}
