<?php

namespace App\Modules\Core;

use App\Foundation\Clock\Clock;
use App\Foundation\Clock\SystemClock;
use App\Foundation\Correlation\CorrelationContext;
use App\Foundation\Features\FeatureRegistry;
use App\Foundation\Health\ReadinessCheck;
use App\Foundation\Health\SystemReadinessCheck;
use App\Foundation\Operations\ProductionSafetyState;
use App\Foundation\Outbox\OutboxEventCatalog;
use App\Modules\Core\Authorization\CompanyPermissionAuthorizer;
use App\Modules\Core\Branch\ActiveBranchContext;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FeatureRegistry::class);
        $this->app->singleton(Clock::class, SystemClock::class);
        $this->app->scoped(CorrelationContext::class);
        $this->app->scoped(ActiveCompanyContext::class);
        $this->app->scoped(ActiveBranchContext::class);
        $this->app->scoped(CompanyPermissionAuthorizer::class);
        $this->app->singleton(OutboxEventCatalog::class);
        $this->app->singleton(ProductionSafetyState::class, static fn (): ProductionSafetyState => new ProductionSafetyState(
            recoveryMode: (bool) config('production.recovery_mode', false),
            outboundProvidersEnabled: (bool) config('production.outbound_providers_enabled', true),
            asyncWorkEnabled: (bool) config('production.async_work_enabled', true),
            schedulerWorkEnabled: (bool) config('production.scheduler_work_enabled', true),
            retryAfterSeconds: (int) config('production.recovery_retry_after_seconds', 300),
            disabledProviders: array_values(array_filter(
                (array) config('production.disabled_providers', []),
                static fn (mixed $provider): bool => is_string($provider) && trim($provider) !== '',
            )),
        ));
        $this->app->singleton(ReadinessCheck::class, SystemReadinessCheck::class);
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        foreach (PermissionKey::cases() as $permission) {
            Gate::define(
                $permission->value,
                function (User $user) use ($permission): bool {
                    if (in_array($permission, [PermissionKey::BackupView, PermissionKey::BackupManage], true)
                        && ! $user->isPlatformAdmin()) {
                        return false;
                    }

                    return $this->app
                        ->make(CompanyPermissionAuthorizer::class)
                        ->allows($user, $permission);
                },
            );
        }
    }
}
