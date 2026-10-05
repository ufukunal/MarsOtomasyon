<?php

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
        'trendyol' => App\Support\Channels\Trendyol\TrendyolAdapter::class,
        'hepsiburada' => App\Support\Channels\Hepsiburada\HepsiburadaAdapter::class,
        'n11' => App\Support\Channels\N11\N11Adapter::class,
        'woocommerce' => null,
    ],

    'retry_delays' => [30, 60, 120],

    'poll_interval_minutes' => 15,

    'poll_lookback_minutes' => 30,
];
