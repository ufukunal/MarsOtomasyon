<?php

use App\Logging\SensitiveDataProcessor;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;

return [
    'default' => env('LOG_CHANNEL', 'stack'),

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => false,
    ],

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', (string) env('LOG_STACK', 'daily')),
            'ignore_exceptions' => false,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
            'replace_placeholders' => true,
            'processors' => [SensitiveDataProcessor::class],
        ],

        'queue' => [
            'driver' => 'daily',
            'path' => storage_path('logs/queue.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 14,
            'replace_placeholders' => true,
            'processors' => [SensitiveDataProcessor::class],
        ],

        'integrity' => [
            'driver' => 'daily',
            'path' => storage_path('logs/integrity.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 90,
            'replace_placeholders' => true,
            'processors' => [SensitiveDataProcessor::class],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'handler_with' => ['stream' => 'php://stderr'],
            'processors' => [SensitiveDataProcessor::class],
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],
    ],
];
