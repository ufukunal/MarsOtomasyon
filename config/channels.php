<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Platform adapters
    |--------------------------------------------------------------------------
    |
    | Platform sınıfları Faz 9 kanal bloklarında tek tek etkinleştirilir.
    | Trendyol Faz 9B, Hepsiburada Faz 9C ile aktif edilmiştir.
    |
    */
    'adapters' => [
        'trendyol' => App\Support\Channels\Trendyol\TrendyolAdapter::class,
        'hepsiburada' => App\Support\Channels\Hepsiburada\HepsiburadaAdapter::class,
        'n11' => null,
        'woocommerce' => null,
    ],

    'retry_delays' => [30, 60, 120],

    'poll_interval_minutes' => 15,

    'poll_lookback_minutes' => 30,
];
