<?php

namespace App\Enums;

enum SalesChannelPlatform: string
{
    case Trendyol = 'trendyol';
    case Hepsiburada = 'hepsiburada';
    case N11 = 'n11';
    case WooCommerce = 'woocommerce';

    public function label(): string
    {
        return match ($this) {
            self::Trendyol => 'Trendyol',
            self::Hepsiburada => 'Hepsiburada',
            self::N11 => 'N11',
            self::WooCommerce => 'WooCommerce',
        };
    }
}
