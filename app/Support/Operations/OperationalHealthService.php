<?php

namespace App\Support\Operations;

use App\Models\HealthCheckRun;
use App\Models\Period;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class OperationalHealthService
{
    public function __construct(
        private readonly BackupHealthService $backup,
        private readonly IntegrityHealthService $integrity,
    ) {}

    /** @return array{status:string,checks:array<string,array<string,mixed>>,correlation_id:string} */
    public function check(bool $persist = false): array
    {
        $correlationId = (string) Str::uuid();

        $checks = [
            'app' => ['ok' => true, 'status' => 'running', 'version' => config('app.version', 'dev')],
            'master' => $this->master(),
            'active_periods' => $this->activePeriods(),
            'valkey' => $this->valkey(),
            'queue_worker' => $this->heartbeat('mars:queue-worker-heartbeat'),
            'scheduler' => $this->heartbeat('mars:scheduler-heartbeat'),
            'operations_worker' => $this->heartbeat('mars:operations-worker-heartbeat'),
            'queue_lag' => $this->queueLag(),
            'failed_jobs' => $this->failedJobs(),
            'disk' => $this->disk(),
            'storage' => $this->storage(),
            'backup' => $this->backup->check(),
            'integrity' => $this->integrity->check(),
        ];

        $failed = collect($checks)->filter(fn (array $check): bool => ($check['severity'] ?? null) === 'failed')->count();
        $unhealthy = collect($checks)->filter(fn (array $check): bool => ! (bool) ($check['ok'] ?? false))->count();

        $status = $failed > 0 ? 'failed' : ($unhealthy > 0 ? 'degraded' : 'healthy');

        if ($persist) {
            HealthCheckRun::query()->create([
                'checked_at' => now(),
                'overall_status' => $status,
                'checks' => $checks,
                'correlation_id' => $correlationId,
            ]);
        }

        return [
            'status' => $status,
            'checks' => $checks,
            'correlation_id' => $correlationId,
        ];
    }

    /** @return array<string, mixed> */
    private function master(): array
    {
        try {
            DB::connection('master')->select('select 1');

            return ['ok' => true, 'status' => 'available'];
        } catch (Throwable) {
            return ['ok' => false, 'status' => 'unavailable', 'severity' => 'failed'];
        }
    }

    /** @return array<string, mixed> */
    private function activePeriods(): array
    {
        $original = config('database.connections.period.database');
        $failed = [];

        try {
            $periods = Period::query()->where('status', 'active')->get();

            foreach ($periods as $period) {
                try {
                    config(['database.connections.period.database' => $period->database_name]);
                    DB::purge('period');
                    DB::connection('period')->select('select 1');
                } catch (Throwable) {
                    $failed[] = (int) $period->id;
                }
            }

            return [
                'ok' => $failed === [],
                'status' => $failed === [] ? 'available' : 'unavailable',
                'failed_period_ids' => $failed,
                'severity' => $failed === [] ? null : 'failed',
            ];
        } catch (Throwable) {
            return [
                'ok' => false,
                'status' => 'unavailable',
                'failed_period_ids' => [],
                'severity' => 'failed',
            ];
        } finally {
            config(['database.connections.period.database' => $original]);
            DB::purge('period');
        }
    }

    /** @return array<string, mixed> */
    private function valkey(): array
    {
        try {
            $ok = (bool) Redis::connection('default')->ping();

            return ['ok' => $ok, 'status' => $ok ? 'available' : 'unavailable', 'severity' => $ok ? null : 'failed'];
        } catch (Throwable) {
            return ['ok' => false, 'status' => 'unavailable', 'severity' => 'failed'];
        }
    }

    /** @return array<string, mixed> */
    private function heartbeat(string $key): array
    {
        try {
            $timestamp = Redis::connection('queue')->get($key);
            if (! $timestamp) {
                return ['ok' => false, 'status' => 'missing', 'severity' => 'failed'];
            }

            $age = max(0, now()->timestamp - (int) $timestamp);
            $maxAge = (int) config('operations.health.heartbeat_max_age_seconds', 180);

            return [
                'ok' => $age <= $maxAge,
                'status' => $age <= $maxAge ? 'fresh' : 'stale',
                'age_seconds' => $age,
                'severity' => $age <= $maxAge ? null : 'failed',
            ];
        } catch (Throwable) {
            return ['ok' => false, 'status' => 'unavailable', 'severity' => 'failed'];
        }
    }

    /** @return array<string, mixed> */
    private function queueLag(): array
    {
        try {
            $redis = Redis::connection('queue');
            $queues = [];
            $total = 0;

            foreach (['default', 'operations'] as $queue) {
                $count = (int) $redis->llen("queues:{$queue}")
                    + (int) $redis->zcard("queues:{$queue}:delayed")
                    + (int) $redis->zcard("queues:{$queue}:reserved");
                $queues[$queue] = $count;
                $total += $count;
            }

            $warn = (int) config('operations.health.queue_lag_warning', 100);

            return [
                'ok' => $total <= $warn,
                'status' => $total <= $warn ? 'normal' : 'high',
                'jobs' => $total,
                'queues' => $queues,
                'severity' => $total <= $warn ? null : 'degraded',
            ];
        } catch (Throwable) {
            return ['ok' => false, 'status' => 'unavailable', 'severity' => 'failed'];
        }
    }

    /** @return array<string, mixed> */
    private function failedJobs(): array
    {
        try {
            $count = DB::connection('master')->table('failed_jobs')->count();

            return [
                'ok' => $count === 0,
                'status' => $count === 0 ? 'clear' : 'present',
                'count' => $count,
                'severity' => $count === 0 ? null : 'degraded',
            ];
        } catch (Throwable) {
            return ['ok' => false, 'status' => 'unavailable', 'severity' => 'failed'];
        }
    }

    /** @return array<string, mixed> */
    private function disk(): array
    {
        $total = disk_total_space(base_path());
        $free = disk_free_space(base_path());

        if ($total === false || $free === false || $total <= 0) {
            return ['ok' => false, 'status' => 'unavailable', 'severity' => 'failed'];
        }

        $used = (($total - $free) / $total) * 100;
        $critical = (float) config('operations.health.disk_critical_percent', 90);
        $warning = (float) config('operations.health.disk_warning_percent', 85);

        return [
            'ok' => $used < $warning,
            'status' => $used >= $critical ? 'critical' : ($used >= $warning ? 'warning' : 'normal'),
            'used_percent' => round($used, 1),
            'free_bytes' => (int) $free,
            'severity' => $used >= $critical ? 'failed' : ($used >= $warning ? 'degraded' : null),
        ];
    }

    /** @return array<string, mixed> */
    private function storage(): array
    {
        $failed = [];

        foreach (config('operations.health.writable_disks', ['attachments', 'report_exports', 'backups']) as $disk) {
            $probe = '.health/'.Str::uuid().'.tmp';

            try {
                Storage::disk($disk)->put($probe, 'health');
                if (! Storage::disk($disk)->exists($probe)) {
                    $failed[] = $disk;
                }
                Storage::disk($disk)->delete($probe);
            } catch (Throwable) {
                $failed[] = $disk;
            }
        }

        return [
            'ok' => $failed === [],
            'status' => $failed === [] ? 'writable' : 'unwritable',
            'failed_disks' => $failed,
            'severity' => $failed === [] ? null : 'failed',
        ];
    }
}
