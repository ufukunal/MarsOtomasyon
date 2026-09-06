<?php

return [
    'features' => [
        'foundation' => true,
        'customers' => true,
        'product_stock' => true,
        'product_family_variant' => true,
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
        'barcode_thermal_labels' => true,
        'mobile_warehouse' => true,
        'light_crm' => true,
        'bi_exports' => true,
        'cad_3d_viewer' => true,
    ],
    'cad' => [
        'max_file_size_bytes' => 52_428_800,
        'timeout_seconds' => 300,
        'retention_days' => 1,
    ],
    'correlation' => [
        'header' => 'X-Correlation-ID',
    ],
];
