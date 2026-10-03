<?php

namespace App\Support\Operations;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class BackupHealthService
{
    public function check(): array
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
            } catch (Throwable $exception) {
                $failed[] = $disk;
                Log::warning('Backup health kontrolü başarısız.', [
                    'disk' => $disk,
                    'exception' => $exception,
                ]);
            }
        }

        return [
            'ok' => $failed === [],
            'status' => $failed === [] ? 'healthy' : 'missing_or_stale',
            'failed_targets' => count($failed),
        ];
    }
}
