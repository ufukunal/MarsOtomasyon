<?php

namespace App\Support\Operations;

use App\Actions\ReferenceData\SeedPeriodReferenceData;
use App\Models\BackupRun;
use App\Models\Period;
use App\Models\RestoreRun;
use App\Support\Period\PeriodContext;
use App\Support\Period\PeriodSchemaVersion;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

final class ArchivePeriodRestoreService
{
    public function __construct(private readonly RecoverySetArchive $archive) {}

    /** @return array<string,mixed> */
    public function restore(BackupRun $backup, Period $period): array
    {
        if ((string) $period->status !== 'archived') {
            throw new RuntimeException('Archive restore yalnız archived period için çalışır.');
        }

        if (DB::connection('master')->table('pg_database')->where('datname', $period->database_name)->exists()) {
            throw new RuntimeException('Archive period veritabanı zaten bağlı.');
        }

        $run = RestoreRun::query()->create([
            'recovery_set_id' => $backup->recovery_set_id,
            'source_backup_run_id' => $backup->id,
            'target_type' => 'archive',
            'status' => 'preparing',
            'started_at' => now(),
        ]);

        $workspace = storage_path('app/restore-temp/archive-'.str_replace('-', '', (string) Str::uuid()));
        $databaseCreated = false;
        $oldDatabase = config('database.connections.period.database');

        try {
            $extracted = $this->archive->verifyAndExtract($backup, $workspace);
            $dump = $this->archive->findSqlDump($extracted['directory'], (string) $period->database_name);

            $run->forceFill(['status' => 'restoring'])->save();

            DB::connection('master')->statement('CREATE DATABASE "'.$this->identifier($period->database_name).'"');
            $databaseCreated = true;
            $this->restoreSql((string) $period->database_name, $dump);

            $run->forceFill(['status' => 'verifying'])->save();

            config(['database.connections.period.database' => $period->database_name]);
            DB::purge('period');

            if (Artisan::call('migrate', [
                '--database' => 'period',
                '--path' => 'database/migrations/period',
                '--force' => true,
            ]) !== 0) {
                throw new RuntimeException('Archive period migration başarısız.');
            }

            PeriodContext::useSystem((int) $period->company_id, (int) $period->id);
            app(SeedPeriodReferenceData::class)->handle();
            $schemaVersion = app(PeriodSchemaVersion::class)->currentDatabaseVersion();

            DB::connection('master')->transaction(function () use ($period, $schemaVersion): void {
                $locked = Period::query()->lockForUpdate()->findOrFail($period->id);
                if ((string) $locked->status !== 'archived') {
                    throw new RuntimeException('Archive period status restore sırasında değişti.');
                }

                $locked->status = 'closed';
                $locked->schema_version = $schemaVersion;
                $locked->version = (int) $locked->version + 1;
                $locked->save();
            });

            PeriodContext::clear();

            DB::connection('master')->statement(
                'ALTER DATABASE "'.$this->identifier($period->database_name).'" SET default_transaction_read_only = on'
            );

            $summary = [
                'checksum_verified' => true,
                'database_restored' => true,
                'migrations_applied' => true,
                'status' => 'closed',
                'database_default_read_only' => true,
            ];

            $run->forceFill([
                'status' => 'verified',
                'finished_at' => now(),
                'verification_summary' => $summary,
            ])->save();

            return ['restore_run_id' => (int) $run->id, ...$summary];
        } catch (Throwable $exception) {
            PeriodContext::clear();
            $summary = app(OperationalErrorSanitizer::class)->summarize($exception);

            if ($databaseCreated) {
                try {
                    DB::connection('master')->statement(
                        'DROP DATABASE IF EXISTS "'.$this->identifier($period->database_name).'" WITH (FORCE)'
                    );
                } catch (Throwable) {
                }
            }

            $run->forceFill([
                'status' => 'failed',
                'finished_at' => now(),
                'error_summary' => $summary,
            ])->save();

            app(OperationalAlertService::class)->send(
                'archive-restore-failed',
                'Archive period restore başarısız',
                'Restore run #'.$run->id.' başarısız: '.$summary,
                1,
            );

            throw $exception;
        } finally {
            config(['database.connections.period.database' => $oldDatabase]);
            DB::purge('period');
            $this->deleteDirectory($workspace);
        }
    }

    private function restoreSql(string $database, string $sqlFile): void
    {
        $process = new Process([
            'psql',
            '--host='.(string) config('database.connections.master.host'),
            '--port='.(string) config('database.connections.master.port'),
            '--username='.(string) config('database.connections.master.username'),
            '--dbname='.$database,
            '--set=ON_ERROR_STOP=1',
            '--file='.$sqlFile,
        ], base_path(), $this->pgEnv(), null, 1800);

        $process->mustRun();
    }

    /** @return array<string,string> */
    private function pgEnv(): array
    {
        $password = (string) config('database.connections.master.password');

        return $password === '' ? [] : ['PGPASSWORD' => $password];
    }

    private function identifier(string $database): string
    {
        if (! preg_match('/^[A-Za-z0-9_]+$/', $database)) {
            throw new RuntimeException('Archive database identifier geçersiz.');
        }

        return $database;
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($directory);
    }
}
