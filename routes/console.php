<?php

use App\Modules\Core\Models\User;
use App\Modules\Operations\BackupManager;
use App\Modules\Operations\OperationsHealth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('mars:status', function (): void {
    $this->info('MarsOtomasyon foundation is bootable.');
})->purpose('Show the MarsOtomasyon foundation status');

Artisan::command('mars:platform-admin {email} {--revoke}', function (): void {
    $emailArgument = $this->argument('email');
    if (! is_string($emailArgument) || trim($emailArgument) === '') {
        throw new InvalidArgumentException('User email is required.');
    }
    $email = mb_strtolower(trim($emailArgument));
    $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();
    if (! $user instanceof User) {
        throw new InvalidArgumentException('User not found: '.$email);
    }
    $enabled = $this->option('revoke') !== true;
    $user->forceFill(['is_platform_admin' => $enabled])->save();
    $this->info($enabled ? 'Platform administrator granted.' : 'Platform administrator revoked.');
})->purpose('Grant or revoke system-wide platform administrator authority');

Artisan::command('mars:ops-status', function (OperationsHealth $health): void {
    $this->line(json_encode($health->snapshot(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
})->purpose('Show PostgreSQL, Valkey, queue and operations health');

Artisan::command('mars:ops-heartbeat {component=scheduler} {--instance=}', function (OperationsHealth $health): void {
    $instanceOption = $this->option('instance');
    $hostname = gethostname();
    $instance = is_string($instanceOption) && $instanceOption !== ''
        ? $instanceOption
        : (is_string($hostname) && $hostname !== '' ? $hostname : 'mars');
    $componentArgument = $this->argument('component');
    $component = is_string($componentArgument) && $componentArgument !== '' ? $componentArgument : 'scheduler';
    $health->heartbeat($component, $instance, ['pid' => getmypid()]);
    $this->info('Heartbeat recorded.');
})->purpose('Record worker or scheduler heartbeat');

Artisan::command('mars:ops-recover', function (OperationsHealth $health): void {
    $this->info((string) $health->recoverStaleWork().' stale operation rows reclaimed and requeued.');
})->purpose('Reclaim stale integration, notification and automation processing states');

Artisan::command('mars:ops-prune', function (OperationsHealth $health): void {
    $this->info((string) $health->prune().' old operations telemetry rows pruned.');
})->purpose('Prune old non-audit operations telemetry');

Artisan::command('mars:backup {--user=}', function (BackupManager $backups): void {
    $user = $this->option('user');
    $userId = (is_string($user) || is_int($user)) && is_numeric($user) ? (int) $user : null;
    $id = $backups->create($userId);
    $this->info('Backup ready: '.$id);
})->purpose('Create encrypted verified PostgreSQL + private file .marsbak backup');

Artisan::command('mars:restore {backup} {--user=} {--no-safety}', function (BackupManager $backups): void {
    $user = $this->option('user');
    $userId = (is_string($user) || is_int($user)) && is_numeric($user) ? (int) $user : null;
    $backupArgument = $this->argument('backup');
    if (! is_string($backupArgument) || $backupArgument === '') {
        throw new InvalidArgumentException('Backup identifier is required.');
    }
    $backups->restore($backupArgument, $userId, $this->option('no-safety') !== true);
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
})->everyMinute()->name('operations.heartbeat')->withoutOverlapping();

Schedule::call(function () use ($runScheduled): void {
    $runScheduled('operations.metrics', fn (): array => app(OperationsHealth::class)->captureMetrics());
})->everyFiveMinutes()->name('operations.metrics')->withoutOverlapping();

Schedule::call(function () use ($runScheduled): void {
    $runScheduled('operations.recover', fn (): array => ['recovered' => app(OperationsHealth::class)->recoverStaleWork()]);
})->everyFiveMinutes()->name('operations.recover')->withoutOverlapping();

Schedule::call(function () use ($runScheduled): void {
    $runScheduled('operations.prune', fn (): array => ['pruned' => app(OperationsHealth::class)->prune()]);
})->dailyAt('03:20')->name('operations.prune')->withoutOverlapping();
