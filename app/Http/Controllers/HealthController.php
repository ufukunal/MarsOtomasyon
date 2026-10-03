<?php

namespace App\Http\Controllers;

use App\Support\Operations\BackupHealthService;
use App\Support\Operations\IntegrityHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(
        BackupHealthService $backupHealth,
        IntegrityHealthService $integrityHealth,
    ): JsonResponse {
        $checks = [
            'version' => ['ok' => true, 'value' => config('app.version', 'dev')],
            'master' => $this->master(),
            'valkey' => $this->valkey(),
            'queue_worker' => $this->queueWorker(),
            'failed_jobs' => $this->failedJobs(),
            'backup' => $backupHealth->check(),
            'integrity' => $integrityHealth->check(),
        ];

        $healthy = collect($checks)->every(
            fn (array $check): bool => (bool) ($check['ok'] ?? false),
        );

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    /** @return array<string, bool|string> */
    private function master(): array
    {
        try {
            DB::connection('master')->select('select 1');

            return ['ok' => true];
        } catch (Throwable $exception) {
            Log::warning('Health master kontrolü başarısız.', ['exception' => $exception]);

            return ['ok' => false, 'status' => 'unavailable'];
        }
    }

    private function valkey(): array
    {
        try {
            return ['ok' => (bool) Redis::connection('default')->ping()];
        } catch (Throwable $exception) {
            Log::warning('Health Valkey kontrolü başarısız.', ['exception' => $exception]);

            return ['ok' => false, 'status' => 'unavailable'];
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
                'status' => $age <= 180 ? 'fresh' : 'stale',
            ];
        } catch (Throwable $exception) {
            Log::warning('Health queue kontrolü başarısız.', ['exception' => $exception]);

            return ['ok' => false, 'status' => 'unavailable'];
        }
    }

    private function failedJobs(): array
    {
        try {
            $count = DB::connection('master')->table('failed_jobs')->count();

            return [
                'ok' => $count === 0,
                'status' => $count === 0 ? 'clear' : 'failed_jobs_present',
            ];
        } catch (Throwable $exception) {
            Log::warning('Health failed_jobs kontrolü başarısız.', ['exception' => $exception]);

            return ['ok' => false, 'status' => 'unavailable'];
        }
    }
}
