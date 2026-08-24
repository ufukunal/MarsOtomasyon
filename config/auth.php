<?php

use App\Modules\Core\Models\User;

return [
    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => User::class,
        ],
    ],
];
