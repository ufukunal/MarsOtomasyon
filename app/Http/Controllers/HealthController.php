<?php

namespace App\Http\Controllers;

use App\Models\Period;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'version' => [
                'ok' => true,
                'value' => config('app.version', 'dev'),
            ],
            'master' => $this->master(),
            'period' => $this->period(),
            'valkey' => $this->valkey(),
            'queue_worker' => $this->queueWorker(),
            'failed_jobs' => $this->failedJobs(),
            'backup' => $this->backup(),
            'integrity' => $this->integrity(),
        ];

        $healthy = collect($checks)->every(
            fn (array $check): bool => (bool) ($check['ok'] ?? false),
        );

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    private function master(): array
    {
        try {
            DB::connection('master')->select('select 1');

            return ['ok' => true];
        } catch (Throwable $exception) {
            return ['ok' => false, 'error' => $exception->getMessage()];
        }
    }

    private function period(): array
    {
        $period = Period::query()
            ->whereIn('status', ['active', 'closed'])
            ->orderByDesc('year')
            ->first();

        if (! $period) {
            return ['ok' => true, 'status' => 'not_configured'];
        }

        $original = config('database.connections.period.database');

        try {
            config(['database.connections.period.database' => $period->database_name]);
            DB::purge('period');
            DB::connection('period')->select('select 1');

            return [
                'ok' => true,
                'database' => $period->database_name,
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'database' => $period->database_name,
                'error' => $exception->getMessage(),
            ];
        } finally {
            config(['database.connections.period.database' => $original]);
            DB::purge('period');
        }
    }

    private function valkey(): array
    {
        try {
            $pong = Redis::connection('default')->ping();

            return ['ok' => (bool) $pong];
        } catch (Throwable $exception) {
            return ['ok' => false, 'error' => $exception->getMessage()];
        }
    }

    private function queueWorker(): array
    {
        try {
            $timestamp = Redis::connection('queue')->get('mars:queue-worker-heartbeat');

            if (! $timestamp) {
                return ['ok' => false, 'status' => 'heartbeat_missing'];
            }

            $age = now()->timestamp - (int) $timestamp;

            return [
                'ok' => $age <= 180,
                'age_seconds' => $age,
            ];
        } catch (Throwable $exception) {
            return ['ok' => false, 'error' => $exception->getMessage()];
        }
    }

    private function failedJobs(): array
    {
        try {
            $count = DB::connection('master')->table('failed_jobs')->count();

            return [
                'ok' => $count === 0,
                'count' => $count,
            ];
        } catch (Throwable $exception) {
            return ['ok' => false, 'error' => $exception->getMessage()];
        }
    }

    private function backup(): array
    {
        try {
            $files = Storage::disk('backups')->allFiles();

            if ($files === []) {
                return ['ok' => false, 'status' => 'missing'];
            }

            $latest = collect($files)
                ->map(fn (string $file): int => Storage::disk('backups')->lastModified($file))
                ->max();

            $ageHours = (now()->timestamp - (int) $latest) / 3600;

            return [
                'ok' => $ageHours <= 36,
                'age_hours' => round($ageHours, 1),
            ];
        } catch (Throwable $exception) {
            return ['ok' => false, 'error' => $exception->getMessage()];
        }
    }

    private function integrity(): array
    {
        $period = Period::query()
            ->where('status', 'active')
            ->orderByDesc('year')
            ->first();

        if (! $period) {
            return ['ok' => true, 'status' => 'not_configured'];
        }

        $original = config('database.connections.period.database');

        try {
            config(['database.connections.period.database' => $period->database_name]);
            DB::purge('period');

            if (! Schema::connection('period')->hasTable('integrity_reports')) {
                return ['ok' => false, 'status' => 'reports_missing'];
            }

            $latest = DB::connection('period')
                ->table('integrity_reports')
                ->orderByDesc('run_at')
                ->first();

            if (! $latest) {
                return ['ok' => false, 'status' => 'never_run'];
            }

            $ageHours = now()->diffInHours($latest->run_at);

            return [
                'ok' => $ageHours <= 72 && (int) $latest->mismatch_count === 0,
                'age_hours' => $ageHours,
                'mismatch_count' => (int) $latest->mismatch_count,
            ];
        } catch (Throwable $exception) {
            return ['ok' => false, 'error' => $exception->getMessage()];
        } finally {
            config(['database.connections.period.database' => $original]);
            DB::purge('period');
        }
    }
}
