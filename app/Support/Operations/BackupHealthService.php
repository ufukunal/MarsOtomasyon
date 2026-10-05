<?php

namespace App\Support\Operations;

use App\Models\BackupRun;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class BackupHealthService
{
    /** @return array<string,mixed> */
    public function check(): array
    {
        try {
            if (! Schema::connection('master')->hasTable('backup_runs')) {
                return $this->legacyStorageCheck();
            }

            $latest = BackupRun::query()
                ->whereIn('status', ['done', 'verified'])
                ->whereNotNull('finished_at')
                ->latest('finished_at')
                ->first();

            if (! $latest) {
                return [
                    'ok' => false,
                    'status' => 'missing',
                    'failed_targets' => count(config('operations.backup.disks', [])),
                    'severity' => 'failed',
                ];
            }

            $maxAge = (int) config('operations.backup.max_age_hours', 36);
            $ageHours = $latest->finished_at->diffInHours(now());
            $failed = [];

            foreach (config('operations.backup.disks', []) as $disk) {
                $archive = $latest->checksum_manifest[$disk]['path'] ?? null;
                $expected = $latest->checksum_manifest[$disk]['checksum'] ?? null;

                if (! is_string($archive) || ! is_string($expected)) {
                    $failed[] = $disk;
                    continue;
                }

                if (! Storage::disk($disk)->exists($archive)) {
                    $failed[] = $disk;
                    continue;
                }

                if (! hash_equals($expected, Storage::disk($disk)->checksum($archive))) {
                    $failed[] = $disk;
                }
            }

            $fresh = $ageHours <= $maxAge;

            return [
                'ok' => $fresh && $failed === [],
                'status' => ! $fresh ? 'stale' : ($failed === [] ? 'healthy' : 'checksum_or_target_failed'),
                'failed_targets' => count($failed),
                'age_hours' => $ageHours,
                'backup_run_id' => (int) $latest->id,
                'recovery_set_id' => (string) $latest->recovery_set_id,
                'restore_verified_at' => $latest->verified_at?->toIso8601String(),
                'severity' => $fresh && $failed === [] ? null : 'failed',
            ];
        } catch (Throwable) {
            return [
                'ok' => false,
                'status' => 'unavailable',
                'failed_targets' => count(config('operations.backup.disks', [])),
                'severity' => 'failed',
            ];
        }
    }

    /** @return array<string,mixed> */
    private function legacyStorageCheck(): array
    {
        $maxAge = (int) config('operations.backup.max_age_hours', 36);
        $failed = [];

        foreach (config('operations.backup.disks', []) as $disk) {
            try {
                $files = Storage::disk($disk)->allFiles();

                if ($files === []) {
                    $failed[] = $disk;
                    continue;
                }

                $latest = collect($files)
                    ->map(fn (string $file): int => Storage::disk($disk)->lastModified($file))
                    ->max();

                if ((now()->timestamp - (int) $latest) > ($maxAge * 3600)) {
                    $failed[] = $disk;
                }
            } catch (Throwable) {
                $failed[] = $disk;
            }
        }

        return [
            'ok' => $failed === [],
            'status' => $failed === [] ? 'healthy' : 'missing_or_stale',
            'failed_targets' => count($failed),
            'severity' => $failed === [] ? null : 'failed',
        ];
    }
}
