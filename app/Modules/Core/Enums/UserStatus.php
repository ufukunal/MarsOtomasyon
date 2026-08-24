<?php

namespace App\Modules\Core\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
