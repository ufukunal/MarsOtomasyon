<?php

return [
    'api' => [
        'rate_limit_per_minute' => (int) env('M20_API_RATE_LIMIT_PER_MINUTE', 120),
        'token_ttl_days' => (int) env('M20_API_TOKEN_TTL_DAYS', 365),
    ],
    'scanner' => [
        'enrollment_ttl_minutes' => (int) env('M20_SCANNER_ENROLLMENT_TTL_MINUTES', 15),
    ],
];
