<?php

namespace App\Support\Operations;

use App\Models\BackupRun;
use App\Models\Period;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class RecoverySetBackupService
{
    public function run(string $triggerType = 'manual'): array
    {
        if (! in_array($triggerType, ['scheduled', 'deploy', 'period_carry', 'manual'], true)) {
            throw new RuntimeException('Geçersiz backup trigger type.');
        }

        $startedAt = now();
        $recoverySetId = (string) Str::uuid();
        $primaryDisk = (string) config('operations.backup.primary_disk', 'backups');
        $requiredDisks = array_values(config('operations.backup.disks', ['backups', 'backup_external']));

        $periods = Period::query()
            ->whereIn('status', ['active', 'closed'])
            ->orderBy('company_id')->orderBy('year')
            ->get(['id', 'company_id', 'year', 'database_name', 'status'])
            ->map(fn (Period $period): array => [
                'period_id' => (int) $period->id,
                'company_id' => (int) $period->company_id,
                'year' => (int) $period->year,
                'database_name' => (string) $period->database_name,
                'status' => (string) $period->status,
            ])->all();

        $run = BackupRun::query()->create([
            'recovery_set_id' => $recoverySetId,
            'trigger_type' => $triggerType,
            'status' => 'running',
            'started_at' => $startedAt,
            'storage_disk' => $primaryDisk,
            'period_manifest' => $periods,
        ]);

        try {
            if (Artisan::call('backup:run') !== 0) {
                throw new RuntimeException('backup:run başarısız oldu.');
            }

            $archives = [];
            foreach ($requiredDisks as $disk) {
                $path = $this->latestArchive((string) $disk, $startedAt->timestamp);
                if ($path === null) {
                    throw new RuntimeException("Backup hedefinde yeni archive bulunamadı: {$disk}.");
                }
                $archives[$disk] = [
                    'path' => $path,
                    'checksum' => Storage::disk($disk)->checksum($path),
                    'size' => Storage::disk($disk)->size($path),
                ];
            }

            if (count(array_unique(array_column($archives, 'checksum'))) !== 1) {
                throw new RuntimeException('Recovery set disk kopyalarının checksum değerleri uyuşmuyor.');
            }

            $manifest = [
                'recovery_set_id' => $recoverySetId,
                'created_at' => now()->toIso8601String(),
                'application' => config('app.name'),
                'version' => config('app.version'),
                'master_database' => (string) config('database.connections.master.database'),
                'periods' => $periods,
                'archives' => $archives,
                'contains_files' => true,
                'encryption' => config('backup.backup.encryption'),
                'secret_material_in_manifest' => false,
            ];
            $manifestPath = "recovery-sets/{$recoverySetId}/manifest.json";
            $manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            foreach ($requiredDisks as $disk) {
                Storage::disk($disk)->put($manifestPath, $manifestJson);
            }

            $primaryArchive = $archives[$primaryDisk] ?? reset($archives);
            $run->forceFill([
                'status' => 'verified',
                'finished_at' => now(),
                'manifest_path' => $manifestPath,
                'master_backup_path' => $primaryArchive['path'],
                'files_backup_path' => $primaryArchive['path'],
                'checksum_manifest' => $archives,
                'verified_at' => now(),
            ])->save();

            return [
                'backup_run_id' => (int) $run->id,
                'recovery_set_id' => $recoverySetId,
                'status' => 'verified',
                'manifest_path' => $manifestPath,
                'period_count' => count($periods),
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

    private function latestArchive(string $disk, int $notBefore): ?string
    {
        $candidates = collect(Storage::disk($disk)->allFiles())
            ->filter(fn (string $path): bool => str_ends_with(strtolower($path), '.zip'))
            ->map(fn (string $path): array => [
                'path' => $path,
                'modified' => Storage::disk($disk)->lastModified($path),
            ])
            ->filter(fn (array $item): bool => $item['modified'] >= ($notBefore - 5))
            ->sortByDesc('modified')->values();

        return $candidates->first()['path'] ?? null;
    }
}
