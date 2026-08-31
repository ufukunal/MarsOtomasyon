<?php

namespace App\Modules\Operations;

use App\Modules\Operations\Jobs\ExecuteAutomationRun;
use App\Modules\Operations\Jobs\ProcessIntegrationEvent;
use App\Modules\Operations\Jobs\ProcessIntegrationSync;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class OperationsHealth
{
    /** @return array<string,mixed> */
    public function snapshot(): array
    {
        $databaseOk = true;
        $valkeyOk = true;
        try {
            DB::selectOne('SELECT 1 AS ok');
        } catch (Throwable) {
            $databaseOk = false;
        }
        $queueDepth = 0;
        try {
            $connection = Redis::connection('queue');
            $connection->command('ping');
            $queue = (string) config('queue.connections.redis.queue', 'mars-default');
            $queueDepth = (int) $connection->command('llen', ['queues:'.$queue]);
        } catch (Throwable) {
            $valkeyOk = false;
        }
        $failedJobs = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        $integrationPending = Schema::hasTable('integration_events') ? DB::table('integration_events')->whereIn('status', ['received', 'processing', 'failed'])->count() : 0;
        $notificationPending = Schema::hasTable('notification_deliveries') ? DB::table('notification_deliveries')->whereIn('status', ['queued', 'sending', 'failed', 'ambiguous'])->count() : 0;
        $automationPending = Schema::hasTable('automation_runs') ? DB::table('automation_runs')->whereIn('status', ['pending_approval', 'queued', 'approved', 'running', 'failed'])->count() : 0;
        $workerCutoff = now()->subSeconds((int) config('m11.operations.worker_stale_after_seconds', 180));
        $schedulerCutoff = now()->subSeconds((int) config('m11.operations.scheduler_stale_after_seconds', 180));
        $workerAlive = Schema::hasTable('operations_heartbeats') && DB::table('operations_heartbeats')->where('component', 'worker')->where('last_seen_at', '>=', $workerCutoff)->exists();
        $schedulerAlive = Schema::hasTable('operations_heartbeats') && DB::table('operations_heartbeats')->where('component', 'scheduler')->where('last_seen_at', '>=', $schedulerCutoff)->exists();

        return [
            'database_ok' => $databaseOk,
            'valkey_ok' => $valkeyOk,
            'queue_depth' => $queueDepth,
            'failed_jobs' => $failedJobs,
            'integration_pending' => $integrationPending,
            'notification_pending' => $notificationPending,
            'automation_pending' => $automationPending,
            'worker_alive' => $workerAlive,
            'scheduler_alive' => $schedulerAlive,
            'healthy' => $databaseOk && $valkeyOk,
        ];
    }

    /** @param array<string,mixed> $metadata */
    public function heartbeat(string $component, string $instance, array $metadata = []): void
    {
        if (! in_array($component, ['worker', 'scheduler'], true)) {
            throw new \InvalidArgumentException('Unknown operations heartbeat component.');
        }
        DB::table('operations_heartbeats')->upsert([[
            'component' => $component,
            'instance' => mb_substr($instance, 0, 128),
            'last_seen_at' => now(),
            'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]], ['component', 'instance'], ['last_seen_at', 'metadata', 'updated_at']);
    }

    /** @return array<string,mixed> */
    public function captureMetrics(): array
    {
        $snapshot = $this->snapshot();
        DB::table('operations_metrics')->insert([
            'captured_at' => now(),
            'database_ok' => (bool) $snapshot['database_ok'],
            'valkey_ok' => (bool) $snapshot['valkey_ok'],
            'queue_depth' => (int) $snapshot['queue_depth'],
            'failed_jobs' => (int) $snapshot['failed_jobs'],
            'integration_pending' => (int) $snapshot['integration_pending'],
            'notification_pending' => (int) $snapshot['notification_pending'],
            'automation_pending' => (int) $snapshot['automation_pending'],
            'details' => json_encode(['worker_alive' => $snapshot['worker_alive'], 'scheduler_alive' => $snapshot['scheduler_alive']], JSON_THROW_ON_ERROR),
        ]);

        return $snapshot;
    }

    public function recoverStaleWork(): int
    {
        $cutoff = now()->subMinutes(15);

        return DB::transaction(function () use ($cutoff): int {
            $count = 0;

            $eventIds = DB::table('integration_events')
                ->where('status', 'processing')
                ->where('updated_at', '<', $cutoff)
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id');
            foreach ($eventIds as $id) {
                DB::table('integration_events')->where('id', $id)->where('status', 'processing')->update([
                    'status' => 'received',
                    'available_at' => now(),
                    'last_error' => 'Automatically reclaimed stale integration processing state.',
                    'updated_at' => now(),
                ]);
                ProcessIntegrationEvent::dispatch((int) $id)->afterCommit();
                $count++;
            }

            $syncIds = DB::table('integration_sync_effects')
                ->where('status', 'sending')
                ->where('updated_at', '<', $cutoff)
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id');
            foreach ($syncIds as $id) {
                DB::table('integration_sync_effects')->where('id', $id)->where('status', 'sending')->update([
                    'status' => 'queued',
                    'available_at' => now(),
                    'last_error' => 'Automatically reclaimed stale integration sync state.',
                    'updated_at' => now(),
                ]);
                ProcessIntegrationSync::dispatch((int) $id)->afterCommit();
                $count++;
            }

            $deliveryIds = DB::table('notification_deliveries')
                ->where('status', 'sending')
                ->where('updated_at', '<', $cutoff)
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id');
            foreach ($deliveryIds as $id) {
                DB::table('notification_provider_attempts')
                    ->where('delivery_id', $id)
                    ->where('status', 'sending')
                    ->update([
                        'status' => 'ambiguous',
                        'retryable' => false,
                        'failure_class' => 'stale_sending_ambiguous',
                        'error' => 'Worker lease expired after provider send began; provider outcome is ambiguous.',
                        'finished_at' => now(),
                    ]);
                DB::table('notification_deliveries')->where('id', $id)->where('status', 'sending')->update([
                    'status' => 'ambiguous',
                    'failure_class' => 'stale_sending_ambiguous',
                    'manual_retry_required' => true,
                    'last_error' => 'Stale provider send quarantined as ambiguous; automatic retry blocked.',
                    'updated_at' => now(),
                ]);
                $count++;
            }

            $automationIds = DB::table('automation_runs')
                ->where('status', 'running')
                ->where('updated_at', '<', $cutoff)
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id');
            foreach ($automationIds as $id) {
                DB::table('automation_runs')->where('id', $id)->where('status', 'running')->update([
                    'status' => 'queued',
                    'started_at' => null,
                    'finished_at' => null,
                    'last_error' => 'Automatically reclaimed stale automation run state.',
                    'updated_at' => now(),
                ]);
                ExecuteAutomationRun::dispatch((int) $id)->afterCommit();
                $count++;
            }

            return $count;
        });
    }

    public function prune(): int
    {
        $cutoff = now()->subDays((int) config('m11.operations.retention_days', 30));
        $count = 0;
        $count += DB::table('operations_metrics')->where('captured_at', '<', $cutoff)->delete();
        $count += DB::table('scheduler_runs')->where('started_at', '<', $cutoff)->delete();

        return $count;
    }
}
