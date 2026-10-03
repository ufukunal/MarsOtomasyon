<?php

return [
    'health' => [
        'token' => env('HEALTH_TOKEN', ''),
    ],

    'backup' => [
        'disks' => ['backups', 'backup_external'],
        'max_age_hours' => 36,
    ],

    'integrity' => [
        'checks' => ['stock', 'documents', 'contacts', 'numbers', 'files'],
        'max_age_hours' => 72,
    ],
];
