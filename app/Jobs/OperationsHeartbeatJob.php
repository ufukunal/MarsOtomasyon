<?php

namespace App\Jobs;

use App\Support\Operations\OperationalHeartbeatService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Redis;

class OperationsHeartbeatJob implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('operations');
    }

    public function handle(OperationalHeartbeatService $heartbeats): void
    {
        $timestamp = (string) now()->timestamp;

        Redis::connection('queue')->setex(
            'mars:operations-worker-heartbeat',
            180,
            $timestamp,
        );

        $heartbeats->touch('queue.operations', [
            'connection' => 'redis',
            'queue' => 'operations',
        ]);
    }
}
