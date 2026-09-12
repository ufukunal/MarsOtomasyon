<?php

return [
    'current_version' => env('MARS_VERSION', '0.0.0-dev'),
    'current_build' => env('MARS_BUILD_SHA', 'unknown'),
    'channel' => env('MARS_UPDATE_CHANNEL', 'stable'),
    'manifest_url' => env('MARS_UPDATE_MANIFEST_URL'),
    'public_key_path' => env('MARS_UPDATE_PUBLIC_KEY_PATH'),
    'allowed_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('MARS_UPDATE_ALLOWED_HOSTS', ''))))),
    'timeout' => max(1, min(30, (int) env('MARS_UPDATE_TIMEOUT', 10))),
    'max_manifest_bytes' => max(16384, (int) env('MARS_UPDATE_MAX_MANIFEST_BYTES', 262144)),
    'max_artifact_bytes' => max(1048576, (int) env('MARS_UPDATE_MAX_ARTIFACT_BYTES', 1073741824)),
    'min_free_bytes' => max(268435456, (int) env('MARS_UPDATE_MIN_FREE_BYTES', 1073741824)),
];
