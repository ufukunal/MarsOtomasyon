<?php

namespace App\Modules\Core\Enums;

enum AttachmentTargetType: string
{
    case Company = 'company';
    case Account = 'account';
    case Product = 'product';

    public function label(): string
    {
        return match ($this) {
            self::Company => 'Firma',
            self::Account => 'Cari',
            self::Product => 'Ürün',
        };
    }
}
