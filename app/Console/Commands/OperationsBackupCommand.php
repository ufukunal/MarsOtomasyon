<?php

namespace App\Console\Commands;

use App\Support\Operations\RecoverySetBackupService;
use Illuminate\Console\Command;
use Throwable;

class OperationsBackupCommand extends Command
{
    protected $signature = 'operations:backup {--trigger=manual : scheduled|deploy|period_carry|manual}';
    protected $description = 'Master, active/closed period DB ve dosyaları recovery set olarak yedekler';

    public function handle(RecoverySetBackupService $service): int
    {
        try {
            $result = $service->run((string) $this->option('trigger'));
            $this->info('Recovery set verified: '.$result['recovery_set_id']);
            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Recovery set backup başarısız: '.$exception->getMessage());
            return self::FAILURE;
        }
    }
}
