<?php

return [
    'providers' => [
        'woocommerce' => [
            'label' => 'WooCommerce',
            'status' => 'contract_verified',
            'capabilities' => ['connection_test', 'order_webhook', 'order_polling', 'product_mapping', 'product_publish', 'stock_publish', 'price_publish', 'media_publish', 'invoice_publish', 'return_evidence', 'settlement_evidence'],
        ],
        'trendyol' => [
            'label' => 'Trendyol',
            'status' => 'contract_verified',
            'capabilities' => ['connection_test', 'category_lookup', 'attribute_lookup', 'product_mapping', 'product_contract', 'stock_publish', 'price_publish', 'media_manual', 'order_webhook', 'order_polling', 'order_cancel_contract', 'return_evidence', 'return_create_contract', 'questions_contract', 'invoice_contract', 'settlement_evidence', 'settlement_contract', 'webhook_registration_contract'],
        ],
        'hepsiburada' => [
            'label' => 'Hepsiburada',
            'status' => 'contract_verified',
            'capabilities' => ['connection_test', 'product_mapping', 'listing_read_contract', 'stock_publish', 'price_publish', 'media_manual', 'inventory_upload_status_contract', 'order_polling', 'order_detail_contract', 'claim_contract', 'invoice_contract', 'return_evidence', 'settlement_evidence', 'webhook_basic_auth_contract'],
        ],
        'amazon' => [
            'label' => 'Amazon SP-API',
            'status' => 'contract_verified',
            'capabilities' => ['connection_test', 'lwa_token_contract', 'region_aware_account', 'product_mapping', 'listing_read_contract', 'product_type_contract', 'stock_publish', 'price_publish', 'media_schema_driven', 'order_polling', 'orders_2026_contract', 'fba_fbm_distinction', 'return_evidence', 'returns_report_contract', 'settlement_evidence', 'settlement_report_contract', 'sandbox_contract'],
        ],
        'n11' => [
            'label' => 'n11',
            'status' => 'contract_verified',
            'capabilities' => ['connection_test', 'category_lookup', 'attribute_lookup', 'product_mapping', 'product_contract', 'stock_publish', 'price_publish', 'media_product_contract', 'task_status_contract', 'order_polling', 'order_package_contract', 'return_evidence', 'invoice_contract', 'settlement_evidence'],
        ],
        'pttavm' => [
            'label' => 'PttAVM',
            'status' => 'contract_verified',
            'capabilities' => ['connection_test', 'category_lookup', 'product_mapping', 'product_contract', 'stock_publish', 'price_publish', 'media_product_contract', 'tracking_status_contract', 'order_polling', 'order_detail_contract', 'return_evidence', 'invoice_contract', 'settlement_evidence'],
        ],
        'idefix' => [
            'label' => 'idefix',
            'status' => 'contract_verified',
            'capabilities' => ['connection_test', 'category_lookup', 'attribute_lookup', 'brand_lookup', 'product_mapping', 'product_contract', 'stock_publish', 'price_publish', 'media_product_contract', 'batch_status_contract', 'order_polling', 'shipment_contract', 'cancel_contract', 'return_evidence', 'return_contract', 'questions_contract', 'invoice_contract', 'settlement_evidence'],
        ],
        'allesgo' => [
            'label' => 'Allesgo',
            'status' => 'contract_verified',
            'capabilities' => ['connection_test', 'category_lookup', 'product_mapping', 'product_contract', 'stock_publish', 'price_publish', 'media_product_contract', 'variant_contract', 'order_polling', 'order_status_contract', 'return_evidence', 'questions_contract', 'invoice_contract', 'settlement_evidence', 'sandbox_contract'],
        ],
    ],
];
