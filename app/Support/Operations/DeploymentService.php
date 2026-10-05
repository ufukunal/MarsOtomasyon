<?php

namespace App\Support\Operations;

use App\Models\DeploymentRun;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Throwable;

final class DeploymentService
{
    public function deploy(
        string $releaseId,
        string $commitSha,
        ?string $previousReleaseId,
        callable $activateRelease,
        ?callable $restartWorkers = null,
    ): array {
        if ($releaseId === '' || ! preg_match('/^[A-Za-z0-9._-]+$/', $releaseId)) {
            throw new RuntimeException('Geçersiz release_id.');
        }
        if (! preg_match('/^[a-f0-9]{7,64}$/i', $commitSha)) {
            throw new RuntimeException('Geçersiz commit SHA.');
        }

        $actor = auth()->user();
        $run = DeploymentRun::query()->create([
            'release_id' => $releaseId,
            'commit_sha' => $commitSha,
            'initiated_by' => $actor?->id,
            'initiated_by_name' => $actor?->name,
            'status' => 'preparing',
            'started_at' => now(),
            'previous_release_id' => $previousReleaseId,
            'metadata' => ['activated' => false],
        ]);

        try {
            $backup = app(RecoverySetBackupService::class)->run('deploy');
            $run->forceFill([
                'status' => 'migrating',
                'metadata' => [
                    'activated' => false,
                    'backup_run_id' => $backup['backup_run_id'],
                    'recovery_set_id' => $backup['recovery_set_id'],
                ],
            ])->save();

            if (Artisan::call('migrate', ['--database' => 'master', '--force' => true]) !== 0) {
                throw new RuntimeException('Master migration başarısız.');
            }
            if (Artisan::call('migrate:periods', ['--force' => true]) !== 0) {
                throw new RuntimeException('Bir veya daha fazla period migration başarısız.');
            }

            $run->forceFill(['status' => 'verifying'])->save();

            if (Artisan::call('optimize') !== 0) {
                throw new RuntimeException('Production cache warmup başarısız.');
            }
            if (Artisan::call('integrity:all', ['--include-closed' => true]) !== 0) {
                throw new RuntimeException('Deploy integrity doğrulaması başarısız.');
            }
            if (Artisan::call('operations:security-check') !== 0) {
                throw new RuntimeException('Production security doğrulaması başarısız.');
            }
            if (Artisan::call('operations:smoke') !== 0) {
                throw new RuntimeException('Candidate release smoke doğrulaması başarısız.');
            }

            $preHealth = app(OperationalHealthService::class)->check();

            $run->forceFill([
                'metadata' => array_merge($run->metadata ?? [], [
                    'pre_activation_health' => $preHealth['status'],
                    'pre_activation_health_correlation_id' => $preHealth['correlation_id'],
                ]),
            ])->save();

            $activateRelease();

            if ($restartWorkers !== null) {
                $restartWorkers();
            }

            $health = $this->waitForHealthy();

            if (Artisan::call('operations:smoke') !== 0) {
                throw new RuntimeException('Post-activation smoke doğrulaması başarısız.');
            }

            $run->forceFill([
                'status' => 'active',
                'finished_at' => now(),
                'metadata' => array_merge($run->metadata ?? [], [
                    'activated' => true,
                    'post_activation_health' => $health['status'],
                    'post_activation_health_correlation_id' => $health['correlation_id'],
                ]),
            ])->save();

            return [
                'deployment_run_id' => (int) $run->id,
                'release_id' => $releaseId,
                'status' => 'active',
                'previous_release_id' => $previousReleaseId,
            ];
        } catch (Throwable $exception) {
            $run->forceFill([
                'status' => 'failed',
                'finished_at' => now(),
                'error_summary' => mb_substr(trim($exception->getMessage()), 0, 500),
            ])->save();
            throw $exception;
        }
    }
    /** @return array{status:string,checks:array<string,array<string,mixed>>,correlation_id:string} */
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
            'Post-activation operational health healthy duruma ulaşmadı: '.
            ($last['status'] ?? 'unknown'),
        );
    }
}
