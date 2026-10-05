<?php

use App\Support\Printing\Drivers\BrowserDriver;
use App\Support\Printing\Drivers\TextDriver;
use App\Support\Printing\Drivers\ZplDriver;

return [
    'driver' => env('PRINTING_DRIVER', 'browser'),
    'driver_class' => BrowserDriver::class,

    'drivers' => [
        'browser' => BrowserDriver::class,
        'zpl' => ZplDriver::class,
        'text' => TextDriver::class,
    ],
];
