<?php

namespace App\Modules\Accounts\Enums;

enum AccountAddressType: string
{
    case Billing = 'billing';
    case Shipping = 'shipping';

    public function label(): string
    {
        return match ($this) {
            self::Billing => 'Fatura',
            self::Shipping => 'Sevk',
        };
    }
}
