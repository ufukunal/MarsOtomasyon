<?php

return [
    'features' => [
        'foundation' => true,
        'customers' => true,
        'product_stock' => true,
        'sales' => true,
        'purchasing' => false,
        'production' => false,
        'treasury' => false,
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
