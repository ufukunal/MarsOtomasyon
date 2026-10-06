<?php

namespace App\Console\Commands;

use App\Models\DeploymentRun;
use App\Support\Operations\OperationalHealthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class OperationsRollbackCommand extends Command
{
    protected $signature = 'operations:rollback
        {--releases-root=/var/www/mars/releases : Immutable release root}
        {--current-link=/var/www/mars/current : Current symlink}
        {--schema-compatible : Kod rollbackinin mevcut schema ile uyumlu olduğu açık onayı}';

    protected $description = 'Migration down çalıştırmadan previous immutable release symlinkine döner';

    public function handle(): int
    {
        if (! $this->option('schema-compatible')) {
            $this->error('Otomatik rollback yalnız --schema-compatible açık onayıyla çalışır.');
            $this->line('Schema/data riski varsa verified recovery-set restore runbook kullanılmalıdır.');

            return self::FAILURE;
        }

        $active = DeploymentRun::query()
            ->where('status', 'active')
            ->latest('id')
            ->first();

        if (! $active || ! $active->previous_release_id) {
            $this->error('Rollback için active deployment ve previous_release_id bulunamadı.');

            return self::FAILURE;
        }

        $root = rtrim((string) $this->option('releases-root'), '/');
        $releasePath = $root.'/'.$active->previous_release_id;
        $currentLink = (string) $this->option('current-link');

        if (! is_dir($releasePath) || ! is_file($releasePath.'/artisan')) {
            $this->error('Previous immutable release dizini bulunamadı: '.$releasePath);

            return self::FAILURE;
        }

        try {
            $this->activate($releasePath, $currentLink);

            $this->restartServices();
            $health = $this->waitForHealthy();

            if (Artisan::call('operations:smoke') !== 0) {
                throw new RuntimeException('Previous release smoke kontrolü başarısız.');
            }

            $active->forceFill([
                'status' => 'rolled_back',
                'finished_at' => now(),
                'metadata' => array_merge($active->metadata ?? [], [
                    'rolled_back_to' => $active->previous_release_id,
                    'schema_down_executed' => false,
                    'post_rollback_health' => $health['status'],
                    'health_correlation_id' => $health['correlation_id'],
                ]),
            ])->save();

            $this->info('Rollback tamamlandı: '.$active->previous_release_id);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Rollback başarısız: '.$exception->getMessage());
            $this->line('Migration down otomatik çalıştırılmadı. Recovery escalation runbook izlenmelidir.');

            return self::FAILURE;
        }
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

    /** @return array<string, mixed> */
    private function waitForHealthy(): array
    {
        $last = null;

        for ($attempt = 0; $attempt < 18; $attempt++) {
            $last = app(OperationalHealthService::class)->check();

            if ($last['status'] === 'healthy') {
                return $last;
            }

            sleep(5);
        }

        throw new RuntimeException(
            'Rollback sonrası operational health healthy duruma ulaşmadı: '.
            $last['status'],
        );
    }

    private function activate(string $releasePath, string $currentLink): void
    {
        $temporary = $currentLink.'.rollback';

        if (is_link($temporary) || file_exists($temporary)) {
            @unlink($temporary);
        }

        if (! symlink($releasePath, $temporary)) {
            throw new RuntimeException('Rollback symlink oluşturulamadı.');
        }

        if (! rename($temporary, $currentLink)) {
            @unlink($temporary);
            throw new RuntimeException('Rollback current symlink atomik değiştirilemedi.');
        }
    }
}
