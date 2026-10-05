<?php

namespace App\Console\Commands;

use App\Models\BackupRun;
use App\Support\Operations\RestoreVerificationService;
use Illuminate\Console\Command;
use Throwable;

class OperationsRestoreVerifyCommand extends Command
{
    protected $signature = 'operations:restore-verify {backup_run_id : Verified recovery-set backup run ID}';
    protected $description = 'Recovery seti temporary DBlerde restore/migrate/integrity ile doğrular';

    public function handle(RestoreVerificationService $service): int
    {
        try {
            $backup = BackupRun::query()->findOrFail((int) $this->argument('backup_run_id'));
            $result = $service->verify($backup);
            $this->info('Restore verification başarılı. restore_run_id='.$result['restore_run_id']);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Restore verification başarısız: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
