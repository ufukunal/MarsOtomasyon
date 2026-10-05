<?php

namespace App\Jobs;

use App\Support\Operations\RecoverySetBackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunRecoverySetBackupJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public function __construct(public readonly string $triggerType = 'manual')
    {
        $this->onQueue('operations');
    }

    public function handle(RecoverySetBackupService $service): void
    {
        $service->run($this->triggerType);
    }
}
