<?php

return [
    'current_version' => env('MARS_VERSION', '0.0.0-dev'),
    'channel' => env('MARS_UPDATE_CHANNEL', 'stable'),
    'manifest_url' => env('MARS_UPDATE_MANIFEST_URL'),
    'public_key_path' => env('MARS_UPDATE_PUBLIC_KEY_PATH'),
    'timeout' => (int) env('MARS_UPDATE_TIMEOUT', 10),
];
