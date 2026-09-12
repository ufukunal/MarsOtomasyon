<?php

use App\Modules\Core\CoreServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\UpdateCenterServiceProvider;

return [
    AppServiceProvider::class,
    CoreServiceProvider::class,
    UpdateCenterServiceProvider::class,
];
