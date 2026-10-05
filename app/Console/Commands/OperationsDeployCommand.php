<?php

namespace App\Console\Commands;

use App\Support\Operations\DeploymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class OperationsDeployCommand extends Command
{
    protected $signature = 'operations:deploy
        {--release= : Release identifier}
        {--commit= : Git commit SHA}
        {--previous= : Previous release identifier}
        {--release-path= : Candidate immutable release directory}
        {--current-link=/var/www/mars/current : Atomic current symlink path}';

    protected $description = 'Backup, Master/period migration, verification ve atomik current geçişini yürütür';

    public function handle(DeploymentService $service): int
    {
        $releasePath = rtrim((string) $this->option('release-path'), '/');
        $currentLink = (string) $this->option('current-link');

        if ($releasePath === '' || ! is_dir($releasePath) || ! is_file($releasePath.'/artisan')) {
            $this->error('Geçerli candidate release path zorunludur.');
            return self::FAILURE;
        }

        try {
            $this->bootstrapReadinessTables();

            $result = $service->deploy(
                releaseId: (string) $this->option('release'),
                commitSha: (string) $this->option('commit'),
                previousReleaseId: $this->option('previous') ?: null,
                activateRelease: fn () => $this->activate($releasePath, $currentLink),
                restartWorkers: fn (): void => $this->restartServices(),
            );
            $this->info('Release active: '.$result['release_id']);
            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Deploy başarısız: '.$exception->getMessage());
            return self::FAILURE;
        }
    }

    private function bootstrapReadinessTables(): void
    {
        if (Schema::connection('master')->hasTable('deployment_runs')
            && Schema::connection('master')->hasTable('backup_runs')) {
            return;
        }

        $this->warn('Operational readiness tabloları yok; güvenli bootstrap başlatılıyor.');

        if (Artisan::call('backup:run') !== 0) {
            throw new RuntimeException('Readiness bootstrap öncesi recovery backup başarısız.');
        }

        if (Artisan::call('migrate', [
            '--database' => 'master',
            '--path' => 'database/migrations/master/0001_12_01_000010_create_operational_readiness_tables.php',
            '--force' => true,
        ]) !== 0) {
            throw new RuntimeException('Operational readiness bootstrap migration başarısız.');
        }

        if (! Schema::connection('master')->hasTable('deployment_runs')
            || ! Schema::connection('master')->hasTable('backup_runs')) {
            throw new RuntimeException('Operational readiness bootstrap tabloları doğrulanamadı.');
        }

        $this->info('Operational readiness bootstrap tamamlandı; tracked deploy akışına geçiliyor.');
    }

    private function restartServices(): void
    {
        if (Artisan::call('queue:restart') !== 0) {
            throw new RuntimeException('Queue restart sinyali gönderilemedi.');
        }

        $process = new Process([
            'sudo',
            'systemctl',
            'restart',
            'mars-queue.service',
            'mars-scheduler.service',
            'mars-operations.service',
        ], base_path(), null, null, 120);

        $process->mustRun();
    }

    private function activate(string $releasePath, string $currentLink): void
    {
        $parent = dirname($currentLink);
        if (! is_dir($parent)) {
            throw new RuntimeException('Current symlink parent dizini bulunamadı.');
        }

        $temporary = $currentLink.'.next';
        if (is_link($temporary) || file_exists($temporary)) {
            @unlink($temporary);
        }
        if (! symlink($releasePath, $temporary)) {
            throw new RuntimeException('Candidate release symlink oluşturulamadı.');
        }
        if (! rename($temporary, $currentLink)) {
            @unlink($temporary);
            throw new RuntimeException('Current symlink atomik olarak değiştirilemedi.');
        }
    }
}
