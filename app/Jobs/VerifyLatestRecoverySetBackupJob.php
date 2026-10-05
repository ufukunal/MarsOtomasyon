<?php

namespace App\Jobs;

use App\Models\BackupRun;
use App\Support\Operations\RestoreVerificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class VerifyLatestRecoverySetBackupJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 7200;

    public function __construct()
    {
        $this->onQueue('operations');
    }

    public function handle(RestoreVerificationService $service): void
    {
        $backup = BackupRun::query()
            ->whereIn('status', ['done', 'verified'])
            ->latest('finished_at')
            ->firstOrFail();

        $service->verify($backup);
    }
}
