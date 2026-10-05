<?php

return [
    'database' => [
        'runtime_username' => env('RUNTIME_DB_USERNAME', env('DB_USERNAME', 'postgres')),
    ],

    'health' => [
        'token' => env('HEALTH_TOKEN', ''),
        'heartbeat_max_age_seconds' => 180,
        'queue_lag_warning' => 100,
        'disk_warning_percent' => 85,
        'disk_critical_percent' => 90,
        'writable_disks' => ['attachments', 'report_exports', 'backups'],
    ],

    'backup' => [
        'primary_disk' => 'backups',
        'disks' => ['backups', 'backup_external'],
        'max_age_hours' => 36,
    ],

    'integrity' => [
        'checks' => [
            'stock', 'costs', 'reservations', 'quarantine', 'units', 'documents',
            'contacts', 'numbers', 'files', 'finance', 'returns', 'imports',
            'recipes', 'production', 'channels', 'channel-orders',
        ],
        'max_age_hours' => 72,
    ],
];
