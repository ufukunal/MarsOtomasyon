<?php

namespace App\Modules\Accounts\Enums;

enum AccountType: string
{
    case Customer = 'customer';
    case Supplier = 'supplier';
    case Mixed = 'mixed';
    case Clearing = 'clearing';
}
