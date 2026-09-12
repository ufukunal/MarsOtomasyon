<?php

return [
    'current_version' => env('MARS_VERSION', '0.0.0-dev'),
    'channel' => env('MARS_UPDATE_CHANNEL', 'stable'),
    'manifest_url' => env('MARS_UPDATE_MANIFEST_URL'),
    'public_key_path' => env('MARS_UPDATE_PUBLIC_KEY_PATH'),
    'allowed_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('MARS_UPDATE_ALLOWED_HOSTS', ''))))),
    'timeout' => (int) env('MARS_UPDATE_TIMEOUT', 10),
    'package_timeout' => (int) env('MARS_UPDATE_PACKAGE_TIMEOUT', 120),
    'max_package_bytes' => (int) env('MARS_UPDATE_MAX_PACKAGE_BYTES', 268435456),
    'max_extracted_bytes' => (int) env('MARS_UPDATE_MAX_EXTRACTED_BYTES', 1073741824),
    'max_files' => (int) env('MARS_UPDATE_MAX_FILES', 20000),
    'staging_root' => env('MARS_UPDATE_STAGING_ROOT', storage_path('app/private/update-center')),
    'agent_enabled' => filter_var(env('MARS_UPDATE_AGENT_ENABLED', false), FILTER_VALIDATE_BOOL),
];
