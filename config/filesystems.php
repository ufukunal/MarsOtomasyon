<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],
        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],
        'mars_backup' => [
            'driver' => 's3',
            'key' => env('MARS_BACKUP_S3_ACCESS_KEY_ID'),
            'secret' => env('MARS_BACKUP_S3_SECRET_ACCESS_KEY'),
            'region' => env('MARS_BACKUP_S3_REGION', 'us-east-1'),
            'bucket' => env('MARS_BACKUP_S3_BUCKET'),
            'endpoint' => env('MARS_BACKUP_S3_ENDPOINT'),
            'use_path_style_endpoint' => (bool) env('MARS_BACKUP_S3_PATH_STYLE', false),
            'throw' => true,
            'report' => false,
        ],
    ],
    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],
];
