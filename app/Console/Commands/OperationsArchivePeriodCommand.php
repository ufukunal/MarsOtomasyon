<?php

namespace App\Console\Commands;

use App\Models\BackupRun;
use App\Models\Period;
use App\Support\Audit\AuditContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class OperationsArchivePeriodCommand extends Command
{
    protected $signature = 'operations:archive-period
        {period_id : Closed period ID}
        {backup_run_id : Restore-verified recovery set backup ID}
        {--force : Interactive confirmation atla}';

    protected $description = 'Restore-verified recovery set bulunan closed period DByi archived/detached duruma alır';

    public function handle(): int
    {
        try {
            $period = Period::query()->findOrFail((int) $this->argument('period_id'));
            $backup = BackupRun::query()->findOrFail((int) $this->argument('backup_run_id'));

            if ((string) $period->status !== 'closed') {
                throw new RuntimeException('Yalnız closed period archived yapılabilir.');
            }

            if ((string) $backup->status !== 'verified' || $backup->verified_at === null) {
                throw new RuntimeException('Archive detach için restore-provası verified recovery set zorunludur.');
            }

            $manifestContainsPeriod = collect($backup->period_manifest ?? [])
                ->contains(fn (array $item): bool =>
                    (string) ($item['database_name'] ?? '') === (string) $period->database_name
                );

            if (! $manifestContainsPeriod) {
                throw new RuntimeException('Seçilen recovery set bu period veritabanını içermiyor.');
            }

            $exists = DB::connection('master')
                ->table('pg_database')
                ->where('datname', $period->database_name)
                ->exists();

            if (! $exists) {
                throw new RuntimeException('Period DB zaten detached; Master status ayrıca incelenmelidir.');
            }

            if (! $this->option('force') && ! $this->confirm(
                "{$period->database_name} fiziksel DB detach/drop edilip archived işaretlensin mi?"
            )) {
                $this->warn('Archive işlemi iptal edildi.');
                return self::SUCCESS;
            }

            DB::connection('master')->transaction(function () use ($period): void {
                $locked = Period::query()->lockForUpdate()->findOrFail($period->id);

                if ((string) $locked->status !== 'closed') {
                    throw new RuntimeException('Period status archive claim sırasında değişti.');
                }

                $locked->status = 'archived';
                $locked->version = (int) $locked->version + 1;
                $locked->save();
            });

            try {
                DB::connection('master')->statement(
                    'DROP DATABASE "'.$this->identifier((string) $period->database_name).'" WITH (FORCE)'
                );
            } catch (Throwable $exception) {
                Period::query()
                    ->whereKey($period->id)
                    ->where('status', 'archived')
                    ->update([
                        'status' => 'closed',
                        'version' => DB::raw('version + 1'),
                        'updated_at' => now(),
                    ]);

                throw $exception;
            }

            AuditContext::master(
                'Closed period restore-verified recovery set sonrasında archived/detached yapıldı.',
                [
                    'period_id' => (int) $period->id,
                    'database_name' => (string) $period->database_name,
                    'backup_run_id' => (int) $backup->id,
                    'recovery_set_id' => (string) $backup->recovery_set_id,
                ],
                $period->refresh(),
                'period_archived',
            );

            $this->info('Period archived/detached: '.$period->database_name);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Archive period işlemi başarısız: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    private function identifier(string $database): string
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $database)) {
            throw new RuntimeException('Archive database identifier geçersiz.');
        }

        return $database;
    }
}
