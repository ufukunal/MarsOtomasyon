<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Redis;

class QueueHeartbeatJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Redis::connection('queue')->setex(
            'mars:queue-worker-heartbeat',
            180,
            (string) now()->timestamp,
        );
    }
}
