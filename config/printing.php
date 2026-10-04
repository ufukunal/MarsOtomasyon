<?php

use App\Support\Printing\Drivers\BrowserDriver;

return [
    'driver' => env('PRINTING_DRIVER', 'browser'),
    'driver_class' => BrowserDriver::class,
];
