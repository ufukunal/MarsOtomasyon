<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Foundation bindings are added in their dedicated M0 gates.
    }

    public function boot(): void
    {
        //
    }
}
