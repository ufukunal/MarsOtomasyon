<?php

namespace App\Modules\B2B\Enums;

enum B2BPermission: string
{
    case PlaceOrders = 'orders.place';
    case ViewPrices = 'prices.view';
    case ViewStock = 'stock.view';
    case ViewBalance = 'balance.view';
    case ViewStatement = 'statement.view';
    case ViewInvoices = 'invoices.view';
    case ViewOrderHistory = 'orders.history';
    case ManageAddresses = 'addresses.manage';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $permission): string => $permission->value, self::cases());
    }
}
