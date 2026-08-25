<?php

namespace App\Modules\Accounts\Enums;

enum AccountType: string
{
    case Customer = 'customer';
    case Supplier = 'supplier';
    case Mixed = 'mixed';
    case Clearing = 'clearing';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Müşteri',
            self::Supplier => 'Tedarikçi',
            self::Mixed => 'Müşteri / Tedarikçi',
            self::Clearing => 'Takas / Clearing',
        };
    }
}
