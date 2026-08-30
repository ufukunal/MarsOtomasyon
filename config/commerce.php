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
            'status' => 'contract_verified',
            'capabilities' => [
                'connection_test',
                'category_lookup',
                'attribute_lookup',
                'product_mapping',
                'product_publish',
                'stock_publish',
                'price_publish',
                'media_publish',
                'order_webhook',
                'order_polling',
                'order_cancel',
                'return_evidence',
                'return_create',
                'questions',
                'invoice_publish',
                'settlement_evidence',
                'webhook_registration',
            ],
        ],
    ],
];
