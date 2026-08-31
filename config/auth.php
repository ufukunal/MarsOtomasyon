<?php

use App\Modules\B2B\Models\B2BUser;
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
        'b2b' => [
            'driver' => 'session',
            'provider' => 'b2b_users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => User::class,
        ],
        'b2b_users' => [
            'driver' => 'eloquent',
            'model' => B2BUser::class,
        ],
    ],
];
