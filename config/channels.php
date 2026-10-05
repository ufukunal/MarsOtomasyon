<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Platform adapters
    |--------------------------------------------------------------------------
    |
    | Platform sınıfları Faz 9 kanal bloklarında tek tek etkinleştirilir.
    | Trendyol Product/Order V2 adapterı Faz 9B ile aktif edilir.
    |
    */
    'adapters' => [
        'trendyol' => App\Support\Channels\Trendyol\TrendyolAdapter::class,
        'hepsiburada' => null,
        'n11' => null,
        'woocommerce' => null,
    ],

    'retry_delays' => [30, 60, 120],

    'poll_interval_minutes' => 15,
];
