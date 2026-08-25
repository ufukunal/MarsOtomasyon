<?php

namespace App\Modules\Products\Enums;

enum ProductStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Inactive => 'Pasif',
        };
    }
}
