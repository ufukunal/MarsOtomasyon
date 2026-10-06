<?php

use App\Support\Channels\Hepsiburada\HepsiburadaAdapter;
use App\Support\Channels\N11\N11Adapter;
use App\Support\Channels\Trendyol\TrendyolAdapter;
use App\Support\Channels\WooCommerce\WooCommerceAdapter;

return [
    /*
    |--------------------------------------------------------------------------
    | Platform adapters
    |--------------------------------------------------------------------------
    |
    | Platform sınıfları Faz 9 kanal bloklarında tek tek etkinleştirilir.
    | Trendyol Faz 9B, Hepsiburada Faz 9C, N11 Faz 9D ile aktif edilmiştir.
    |
    */
    'adapters' => [
        'trendyol' => TrendyolAdapter::class,
        'hepsiburada' => HepsiburadaAdapter::class,
        'n11' => N11Adapter::class,
        'woocommerce' => WooCommerceAdapter::class,
    ],

    'retry_delays' => [30, 60, 120],

    'poll_interval_minutes' => 15,

    'poll_lookback_minutes' => 30,
];
