<?php

namespace App\Console\Commands;

use App\Models\BackupRun;
use App\Models\Period;
use App\Support\Operations\ArchivePeriodRestoreService;
use Illuminate\Console\Command;
use Throwable;

class OperationsArchiveRestoreCommand extends Command
{
    protected $signature = 'operations:archive-restore
        {backup_run_id : Recovery-set backup run ID}
        {period_id : Archived period ID}
        {--force : Interactive confirmation atla}';

    protected $description = 'Archived period DByi recovery setten restore/migrate edip closed read-only olarak bağlar';

    public function handle(ArchivePeriodRestoreService $service): int
    {
        try {
            $backup = BackupRun::query()->findOrFail((int) $this->argument('backup_run_id'));
            $period = Period::query()->findOrFail((int) $this->argument('period_id'));

            if (! $this->option('force') && ! $this->confirm(
                "{$period->database_name} archived period veritabanı restore edilsin mi?"
            )) {
                $this->warn('Archive restore iptal edildi.');
                return self::SUCCESS;
            }

            $result = $service->restore($backup, $period);
            $this->info('Archive period restore başarılı. restore_run_id='.$result['restore_run_id']);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Archive restore başarısız: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
