<?php

use App\Jobs\QueueHeartbeatJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schedule;

Artisan::command('mars:about', function (): void {
    $this->info('MarsOtomasyon');
})->purpose('MarsOtomasyon uygulama bilgisini gösterir');

Schedule::command('backup:clean')->daily()->at('01:00');
Schedule::command('backup:run')->daily()->at('01:30');
Schedule::command('backup:monitor')->daily()->at('02:00');
Schedule::command('integrity:all')->dailyAt('03:00');
Schedule::command('idempotency:prune')->dailyAt('03:30');
Schedule::command('reports:prune-exports')->dailyAt('04:00');
Schedule::call(static function (): void {
    Redis::connection('queue')->setex(
        'mars:scheduler-heartbeat',
        180,
        (string) now()->timestamp,
    );
})->name('scheduler-heartbeat')->everyMinute();
Schedule::job(new QueueHeartbeatJob)->name('queue-worker-heartbeat')->everyMinute();
Schedule::command('operations:monitor')->everyTenMinutes();
Schedule::command('channels:poll')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('channels:retry')->everyMinute()->withoutOverlapping();
