<?php

namespace App\Modules\Accounts\Enums;

enum AccountStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
