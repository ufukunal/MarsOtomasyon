<?php

return [
    'providers' => [
        'woocommerce' => [
            'label' => 'WooCommerce',
            'status' => 'm17',
            'capabilities' => [
                'connection_test',
                'order_webhook',
                'order_polling',
                'product_mapping',
                'product_publish',
                'stock_publish',
                'price_publish',
                'media_publish',
                'invoice_publish',
                'return_evidence',
                'settlement_evidence',
            ],
        ],
        'trendyol' => [
            'label' => 'Trendyol',
            'status' => 'transport_only',
            'capabilities' => [
                'order_webhook',
            ],
        ],
    ],
];
