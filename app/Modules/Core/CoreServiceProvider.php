<?php

namespace App\Modules\Core;

use App\Foundation\Features\FeatureRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

final class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FeatureRegistry::class);
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
    }
}
