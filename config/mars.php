<?php

return [
    'features' => [
        'foundation' => true,
        'customers' => true,
        'product_stock' => true,
        'product_family_variant' => (bool) env('FEATURE_PRODUCT_FAMILY_VARIANT', false),
        'sales' => true,
        'purchasing' => true,
        'production' => true,
        'treasury' => true,
        'instruments' => true,
        'returns' => true,
        'import' => true,
        'commerce' => true,
        'communications' => true,
        'automation' => true,
        'operations' => true,
        'reports' => true,
    ],
    'correlation' => [
        'header' => 'X-Correlation-ID',
    ],
];
