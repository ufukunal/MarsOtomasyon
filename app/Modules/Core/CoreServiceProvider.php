<?php

namespace App\Modules\Core;

use App\Foundation\Clock\Clock;
use App\Foundation\Clock\SystemClock;
use App\Foundation\Correlation\CorrelationContext;
use App\Foundation\Features\FeatureRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

final class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FeatureRegistry::class);
        $this->app->singleton(Clock::class, SystemClock::class);
        $this->app->scoped(CorrelationContext::class);
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
    }
}
