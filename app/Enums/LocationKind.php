<?php

namespace App\Enums;

enum LocationKind: string
{
    case Warehouse = 'warehouse';
    case Branch = 'branch';
    case Vehicle = 'vehicle';
}
