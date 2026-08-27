<?php

return [
    'features' => [
        'foundation' => true,
        'customers' => true,
        'product_stock' => true,
        'sales' => true,
        'purchasing' => true,
        'production' => false,
        'treasury' => true,
        'instruments' => false,
        'returns' => false,
        'import' => false,
        'commerce' => false,
        'communications' => false,
        'reports' => false,
    ],
    'correlation' => [
        'header' => 'X-Correlation-ID',
    ],
];
