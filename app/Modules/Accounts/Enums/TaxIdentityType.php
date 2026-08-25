<?php

namespace App\Modules\Accounts\Enums;

enum TaxIdentityType: string
{
    case None = 'none';
    case Vkn = 'vkn';
    case Tckn = 'tckn';
    case Foreign = 'foreign';
}
