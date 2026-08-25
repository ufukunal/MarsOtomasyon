<?php

namespace App\Modules\Accounts\Enums;

enum AccountContactKind: string
{
    case Phone = 'phone';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::Phone => 'Telefon',
            self::Email => 'E-Posta',
        };
    }
}
