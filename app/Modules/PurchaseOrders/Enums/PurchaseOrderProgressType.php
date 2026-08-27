<?php

namespace App\Modules\PurchaseOrders\Enums;

enum PurchaseOrderProgressType: string
{
    case Received = 'received';
    case Invoiced = 'invoiced';
    case Cancelled = 'cancelled';
}
