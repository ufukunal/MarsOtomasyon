<?php

namespace App\Jobs;

use App\Support\Operations\OperationalHeartbeatService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Redis;

class QueueHeartbeatJob implements ShouldQueue
{
    use Queueable;

    public function handle(OperationalHeartbeatService $heartbeats): void
    {
        $timestamp = (string) now()->timestamp;

        Redis::connection('queue')->setex(
            'mars:queue-worker-heartbeat',
            180,
            $timestamp,
        );

        $heartbeats->touch('queue.default', [
            'connection' => 'redis',
            'queue' => 'default',
        ]);
    }
}
