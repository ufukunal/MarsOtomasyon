<?php

return [
    'features' => [
        'foundation' => true,
        'customers' => true,
        'product_stock' => true,
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
        'barcode_thermal_labels' => (bool) env('MARS_FEATURE_BARCODE_THERMAL_LABELS', false),
    ],
    'correlation' => [
        'header' => 'X-Correlation-ID',
    ],
];
