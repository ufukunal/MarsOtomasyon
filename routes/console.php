<?php

use App\Modules\Operations\BackupManager;
use App\Modules\Operations\OperationsHealth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('mars:status', function (): void {
    $this->info('MarsOtomasyon foundation is bootable.');
})->purpose('Show the MarsOtomasyon foundation status');

Artisan::command('mars:ops-status', function (OperationsHealth $health): void {
    $this->line(json_encode($health->snapshot(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
})->purpose('Show PostgreSQL, Valkey, queue and operations health');

Artisan::command('mars:ops-heartbeat {component=scheduler} {--instance=}', function (OperationsHealth $health): void {
    $instance = (string) ($this->option('instance') ?: (gethostname() ?: 'mars'));
    $health->heartbeat((string) $this->argument('component'), $instance, ['pid' => getmypid()]);
    $this->info('Heartbeat recorded.');
})->purpose('Record worker or scheduler heartbeat');

Artisan::command('mars:ops-recover', function (OperationsHealth $health): void {
    $this->info((string) $health->recoverStaleWork().' stale operation rows recovered.');
})->purpose('Recover stale integration, notification and automation processing states');

Artisan::command('mars:ops-prune', function (OperationsHealth $health): void {
    $this->info((string) $health->prune().' old operations telemetry rows pruned.');
})->purpose('Prune old non-audit operations telemetry');

Artisan::command('mars:backup {--user=}', function (BackupManager $backups): void {
    $user = $this->option('user');
    $id = $backups->create(is_numeric($user) ? (int) $user : null);
    $this->info('Backup ready: '.$id);
})->purpose('Create encrypted verified PostgreSQL .marsbak backup');

Artisan::command('mars:restore {backup} {--user=} {--no-safety}', function (BackupManager $backups): void {
    $user = $this->option('user');
    $backups->restore((string) $this->argument('backup'), is_numeric($user) ? (int) $user : null, ! (bool) $this->option('no-safety'));
    $this->info('Restore completed.');
})->purpose('Restore verified .marsbak with optional safety backup');

$runScheduled = function (string $taskKey, callable $callback): void {
    $id = DB::table('scheduler_runs')->insertGetId([
        'task_key' => $taskKey, 'status' => 'running', 'started_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    try {
        $details = $callback();
        DB::table('scheduler_runs')->where('id', $id)->update([
            'status' => 'succeeded', 'finished_at' => now(), 'details' => json_encode(is_array($details) ? $details : ['result' => $details], JSON_THROW_ON_ERROR), 'updated_at' => now(),
        ]);
    } catch (Throwable $exception) {
        DB::table('scheduler_runs')->where('id', $id)->update([
            'status' => 'failed', 'finished_at' => now(), 'details' => json_encode(['error' => mb_substr($exception->getMessage(), 0, 2000)], JSON_THROW_ON_ERROR), 'updated_at' => now(),
        ]);
        throw $exception;
    }
};

Schedule::call(function () use ($runScheduled): void {
    $runScheduled('operations.heartbeat', function (): array {
        $instance = gethostname();
        app(OperationsHealth::class)->heartbeat('scheduler', is_string($instance) && $instance !== '' ? $instance : 'scheduler', ['pid' => getmypid()]);

        return ['heartbeat' => true];
    });
})->everyMinute()->withoutOverlapping()->name('operations.heartbeat');

Schedule::call(function () use ($runScheduled): void {
    $runScheduled('operations.metrics', fn (): array => app(OperationsHealth::class)->captureMetrics());
})->everyFiveMinutes()->withoutOverlapping()->name('operations.metrics');

Schedule::call(function () use ($runScheduled): void {
    $runScheduled('operations.recover', fn (): array => ['recovered' => app(OperationsHealth::class)->recoverStaleWork()]);
})->everyFiveMinutes()->withoutOverlapping()->name('operations.recover');

Schedule::call(function () use ($runScheduled): void {
    $runScheduled('operations.prune', fn (): array => ['pruned' => app(OperationsHealth::class)->prune()]);
})->dailyAt('03:20')->withoutOverlapping()->name('operations.prune');
