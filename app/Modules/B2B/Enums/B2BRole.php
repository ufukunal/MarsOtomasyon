<?php

namespace App\Modules\B2B\Enums;

enum B2BRole: string
{
    case Admin = 'admin';
    case Buyer = 'buyer';
    case Viewer = 'viewer';

    /** @return list<B2BPermission> */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::Admin => B2BPermission::cases(),
            self::Buyer => [B2BPermission::PlaceOrders, B2BPermission::ViewPrices, B2BPermission::ViewStock, B2BPermission::ViewOrderHistory],
            self::Viewer => [B2BPermission::ViewPrices, B2BPermission::ViewStock, B2BPermission::ViewBalance, B2BPermission::ViewStatement, B2BPermission::ViewInvoices, B2BPermission::ViewOrderHistory],
        };
    }
}
