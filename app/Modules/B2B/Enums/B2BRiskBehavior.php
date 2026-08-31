<?php

namespace App\Modules\B2B\Enums;

enum B2BRiskBehavior: string
{
    case Block = 'block';
    case Warn = 'warn';
}
