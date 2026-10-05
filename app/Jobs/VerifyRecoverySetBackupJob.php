<?php

namespace App\Jobs;

use App\Models\BackupRun;
use App\Support\Operations\RestoreVerificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class VerifyRecoverySetBackupJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 7200;

    public function __construct(public readonly int $backupRunId) {}

    public function handle(RestoreVerificationService $service): void
    {
        $backup = BackupRun::query()->findOrFail($this->backupRunId);
        $service->verify($backup);
    }
}
