<?php

use App\Jobs\QueueHeartbeatJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('mars:about', function (): void {
    $this->info('MarsOtomasyon');
})->purpose('MarsOtomasyon uygulama bilgisini gösterir');

Schedule::command('backup:clean')->daily()->at('01:00');
Schedule::command('backup:run')->daily()->at('01:30');
Schedule::command('backup:monitor')->daily()->at('02:00');
Schedule::command('integrity:all')->dailyAt('03:00');
Schedule::command('idempotency:prune')->dailyAt('03:30');
Schedule::job(new QueueHeartbeatJob)->everyMinute();
Schedule::command('operations:monitor')->everyTenMinutes();
Schedule::command('channels:poll')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('channels:retry')->everyMinute()->withoutOverlapping();
