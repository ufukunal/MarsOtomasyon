<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Platform adapters
    |--------------------------------------------------------------------------
    |
    | Faz 9A yalnız ortak adapter sınırını kurar. Gerçek platform sınıfları
    | Trendyol / Hepsiburada / N11 / WooCommerce bloklarında tek tek eklenir.
    |
    */
    'adapters' => [
        'trendyol' => null,
        'hepsiburada' => null,
        'n11' => null,
        'woocommerce' => null,
    ],

    'retry_delays' => [30, 60, 120],

    'poll_interval_minutes' => 15,
];
