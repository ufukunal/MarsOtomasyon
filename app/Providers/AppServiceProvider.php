<?php

namespace App\Providers;

use App\Modules\Operations\OperationsHealth;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Foundation and module services are container-autowireable.
    }

    public function boot(): void
    {
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
