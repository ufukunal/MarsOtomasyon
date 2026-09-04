<?php

namespace App\Foundation\Health;

use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final readonly class OffsiteBackupReadinessCheck
{
    public function __construct(private FilesystemManager $filesystems) {}

    public function check(): bool
    {
        $diskName = (string) config('m11.backup.disk', 'local');
        $path = 'readiness/'.Str::uuid().'.probe';
        $disk = null;

        try {
            $disk = $this->filesystems->disk($diskName);

            if ($disk->put($path, 'mars-production-readiness') !== true) {
                return false;
            }

            return $disk->exists($path);
        } catch (Throwable $exception) {
            Log::warning('Offsite backup storage readiness probe failed.', [
                'dependency' => 'offsite-backup',
                'exception_class' => $exception::class,
            ]);

            return false;
        } finally {
            if ($disk !== null) {
                try {
                    $disk->delete($path);
                } catch (Throwable) {
                    // Readiness already reflects the primary probe result; never leak provider details here.
                }
            }
        }
    }
}
