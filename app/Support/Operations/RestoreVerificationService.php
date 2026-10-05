<?php

namespace App\Support\Operations;

use App\Models\BackupRun;
use App\Models\RestoreRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

final class RestoreVerificationService
{
    public function __construct(private readonly RecoverySetArchive $archive) {}

    /** @return array<string,mixed> */
    public function verify(BackupRun $backup): array
    {
        $run = RestoreRun::query()->create([
            'recovery_set_id' => $backup->recovery_set_id,
            'source_backup_run_id' => $backup->id,
            'target_type' => 'temporary',
            'status' => 'preparing',
            'started_at' => now(),
        ]);

        $suffix = str_replace('-', '', (string) Str::uuid());
        $workspace = storage_path('app/restore-temp/'.$suffix);
        $created = [];

        try {
            $extracted = $this->archive->verifyAndExtract($backup, $workspace);
            $masterSource = (string) config('database.connections.master.database');
            $masterTarget = $this->tempDbName('master', $suffix);
            $periods = $backup->period_manifest ?? [];

            $run->forceFill(['status' => 'restoring'])->save();

            $this->createDatabase($masterTarget);
            $created[] = $masterTarget;
            $this->restoreSql(
                $masterTarget,
                $this->archive->findSqlDump($extracted['directory'], $masterSource),
            );

            $periodMap = [];
            foreach ($periods as $index => $period) {
                $sourceName = (string) ($period['database_name'] ?? '');
                if ($sourceName === '') {
                    throw new RuntimeException('Recovery set period manifest database_name eksik.');
                }

                $targetName = $this->tempDbName('p'.($index + 1), $suffix);
                $this->createDatabase($targetName);
                $created[] = $targetName;
                $this->restoreSql(
                    $targetName,
                    $this->archive->findSqlDump($extracted['directory'], $sourceName),
                );
                $periodMap[$sourceName] = $targetName;
            }

            foreach ($periodMap as $source => $target) {
                $this->psql(
                    $masterTarget,
                    'UPDATE periods SET database_name = '.DB::connection('master')->getPdo()->quote($target).
                    ' WHERE database_name = '.DB::connection('master')->getPdo()->quote($source),
                );
            }

            $run->forceFill(['status' => 'verifying'])->save();

            $env = [
                'APP_ENV' => 'restore-verify',
                'APP_CONFIG_CACHE' => $workspace.'/config.php',
                'DB_MASTER_DATABASE' => $masterTarget,
                'CACHE_STORE' => 'array',
                'SESSION_DRIVER' => 'array',
                'QUEUE_CONNECTION' => 'sync',
            ];

            $this->artisan(['migrate', '--database=master', '--force'], $env);
            $this->artisan(['migrate:periods', '--force'], $env);
            $this->artisan(['integrity:all', '--include-closed'], $env);

            $summary = [
                'checksum_verified' => true,
                'master_restored' => true,
                'periods_restored' => count($periodMap),
                'migrations_verified' => true,
                'integrity_verified' => true,
                'temporary_databases_destroyed_after_verification' => true,
            ];

            $run->forceFill([
                'status' => 'verified',
                'finished_at' => now(),
                'verification_summary' => $summary,
            ])->save();

            $backup->forceFill(['status' => 'verified', 'verified_at' => now()])->save();

            return ['restore_run_id' => (int) $run->id, ...$summary];
        } catch (Throwable $exception) {
            $run->forceFill([
                'status' => 'failed',
                'finished_at' => now(),
                'error_summary' => mb_substr(trim($exception->getMessage()), 0, 500),
            ])->save();

            throw $exception;
        } finally {
            foreach (array_reverse($created) as $database) {
                $this->dropDatabase($database);
            }
            $this->deleteDirectory($workspace);
        }
    }

    private function tempDbName(string $label, string $suffix): string
    {
        return 'mars_verify_'.$label.'_'.substr($suffix, 0, 16);
    }

    private function createDatabase(string $database): void
    {
        $this->assertIdentifier($database);
        DB::connection('master')->statement('CREATE DATABASE "'.$database.'"');
    }

    private function dropDatabase(string $database): void
    {
        try {
            $this->assertIdentifier($database);
            DB::connection('master')->statement('DROP DATABASE IF EXISTS "'.$database.'" WITH (FORCE)');
        } catch (Throwable) {
        }
    }

    private function restoreSql(string $database, string $sqlFile): void
    {
        $process = new Process($this->psqlArgs($database, ['--file='.$sqlFile]), base_path(), $this->pgEnv(), null, 1800);
        $process->mustRun();
    }

    private function psql(string $database, string $sql): void
    {
        $process = new Process($this->psqlArgs($database, ['--command='.$sql]), base_path(), $this->pgEnv(), null, 120);
        $process->mustRun();
    }

    /** @param list<string> $arguments */
    private function artisan(array $arguments, array $env): void
    {
        $process = new Process(
            [PHP_BINARY, 'artisan', ...$arguments],
            base_path(),
            [...$this->pgEnv(), ...$env],
            null,
            1800,
        );
        $process->mustRun();
    }

    /** @param list<string> $extra */
    private function psqlArgs(string $database, array $extra): array
    {
        return [
            'psql',
            '--host='.(string) config('database.connections.master.host'),
            '--port='.(string) config('database.connections.master.port'),
            '--username='.(string) config('database.connections.master.username'),
            '--dbname='.$database,
            '--set=ON_ERROR_STOP=1',
            ...$extra,
        ];
    }

    /** @return array<string,string> */
    private function pgEnv(): array
    {
        $password = (string) config('database.connections.master.password');

        return $password === '' ? [] : ['PGPASSWORD' => $password];
    }

    private function assertIdentifier(string $value): void
    {
        if (! preg_match('/^[a-z0-9_]+$/', $value)) {
            throw new RuntimeException('Temporary database identifier geçersiz.');
        }
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
