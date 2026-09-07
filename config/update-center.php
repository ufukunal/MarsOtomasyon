<?php

return [
    'current_version' => env('MARS_VERSION', '0.0.0-dev'),
    'channel' => env('MARS_UPDATE_CHANNEL', 'stable'),
    'manifest_url' => env('MARS_UPDATE_MANIFEST_URL'),
    'public_key_path' => env('MARS_UPDATE_PUBLIC_KEY_PATH'),
    'allowed_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('MARS_UPDATE_ALLOWED_HOSTS', ''))))),
    'timeout' => (int) env('MARS_UPDATE_TIMEOUT', 10),
];
