<?php

namespace App\Enums;

enum ProductKind: string
{
    case Normal = 'normal';
    case Set = 'set';
    case Configurable = 'configurable';
}
