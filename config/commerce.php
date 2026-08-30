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
                'product_contract',
                'stock_publish',
                'price_publish',
                'media_manual',
                'order_webhook',
                'order_polling',
                'order_cancel_contract',
                'return_evidence',
                'return_create_contract',
                'questions_contract',
                'invoice_contract',
                'settlement_evidence',
                'settlement_contract',
                'webhook_registration_contract',
            ],
        ],
    ],
];
