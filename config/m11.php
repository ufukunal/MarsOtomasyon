<?php

return [
    'integrations' => [
        'supported_providers' => ['woocommerce', 'trendyol', 'hepsiburada', 'amazon'],
        'supported_operations' => ['order', 'product', 'price', 'stock', 'invoice', 'refund'],
        'max_payload_bytes' => (int) env('MARS_INTEGRATION_MAX_PAYLOAD_BYTES', 1048576),
        'retry_delays' => [60, 300, 900, 3600],
    ],
    'notifications' => [
        'max_attempts' => (int) env('MARS_NOTIFICATION_MAX_ATTEMPTS', 5),
        'sms' => [
            'endpoint' => env('MARS_SMS_ENDPOINT'),
            'token' => env('MARS_SMS_TOKEN'),
        ],
        'whatsapp' => [
            'endpoint' => env('MARS_WHATSAPP_ENDPOINT'),
            'token' => env('MARS_WHATSAPP_TOKEN'),
        ],
    ],
    'operations' => [
        'worker_stale_after_seconds' => (int) env('MARS_WORKER_STALE_AFTER', 180),
        'scheduler_stale_after_seconds' => (int) env('MARS_SCHEDULER_STALE_AFTER', 180),
        'retention_days' => (int) env('MARS_OPERATIONS_RETENTION_DAYS', 30),
    ],
    'backup' => [
        'disk' => env('MARS_BACKUP_DISK', 'local'),
        'directory' => env('MARS_BACKUP_DIRECTORY', 'backups'),
        'pg_dump' => env('MARS_PG_DUMP_BINARY', 'pg_dump'),
        'psql' => env('MARS_PSQL_BINARY', 'psql'),
        'include_file_assets' => (bool) env('MARS_BACKUP_INCLUDE_FILE_ASSETS', true),
        'max_file_assets_bytes' => (int) env('MARS_BACKUP_MAX_FILE_ASSETS_BYTES', 1073741824),
    ],
];
