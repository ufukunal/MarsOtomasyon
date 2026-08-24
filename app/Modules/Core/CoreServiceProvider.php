<?php

namespace App\Modules\Core;

use App\Foundation\Clock\Clock;
use App\Foundation\Clock\SystemClock;
use App\Foundation\Correlation\CorrelationContext;
use App\Foundation\Features\FeatureRegistry;
use App\Foundation\Health\ReadinessCheck;
use App\Foundation\Health\SystemReadinessCheck;
use App\Foundation\Outbox\OutboxEventCatalog;
use App\Modules\Core\Company\ActiveCompanyContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

final class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FeatureRegistry::class);
        $this->app->singleton(Clock::class, SystemClock::class);
        $this->app->scoped(CorrelationContext::class);
        $this->app->scoped(ActiveCompanyContext::class);
        $this->app->singleton(OutboxEventCatalog::class);
        $this->app->singleton(ReadinessCheck::class, SystemReadinessCheck::class);
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
    }
}
