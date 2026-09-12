<?php

namespace App\Providers;

use App\Modules\Core\Models\User;
use App\Modules\Operations\OperationsHealth;
use App\Modules\Reports\Bi\AccountAgingDataset;
use App\Modules\Reports\Bi\BiDatasetRegistry;
use App\Modules\Reports\Bi\BiScheduleRunner;
use App\Modules\Reports\Bi\SalesInvoiceDataset;
use App\Modules\Reports\ReportService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BiDatasetRegistry::class, function (): BiDatasetRegistry {
            return (new BiDatasetRegistry)
                ->register(new SalesInvoiceDataset)
                ->register(new AccountAgingDataset($this->app->make(ReportService::class)));
        });
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        View::composer('operations.index', function ($view): void {
            $user = Auth::user();
            if (! $user instanceof User || ! $user->isPlatformAdmin()) {
                $view->with('backups', collect());
            }
        });

        Schedule::call(static fn (): array => app(BiScheduleRunner::class)->runDue())
            ->everyFiveMinutes()
            ->name('reports.bi.exports')
            ->withoutOverlapping();

        Queue::looping(function (): void {
            static $lastHeartbeatAt = 0;
            if (time() - $lastHeartbeatAt < 30) {
                return;
            }
            $instance = gethostname();
            app(OperationsHealth::class)->heartbeat('worker', is_string($instance) && $instance !== '' ? $instance : 'worker-'.getmypid(), ['pid' => getmypid()]);
            $lastHeartbeatAt = time();
        });
    }
}
