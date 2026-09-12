<?php

namespace App\Foundation\Operations;

use Illuminate\Contracts\Foundation\Application;

final readonly class ProductionCandidateGate
{
    public function __construct(private Application $app) {}

    /** @return list<string> */
    public function issues(): array
    {
        $issues = [];
        $deploymentModel = (string) config('production.deployment_model', '');
        $primaryDisk = (string) config('production.primary_file_disk', 'local');
        $backupDisk = (string) config('m11.backup.disk', 'local');
        $offsiteRequired = (bool) config('production.backup.offsite_required', true);
        $offsiteTarget = trim((string) config('production.backup.offsite_target', ''));
        $recoveryKeyReference = trim((string) config('production.backup.recovery_key_reference', ''));
        $backupDriver = trim((string) config('filesystems.disks.'.$backupDisk.'.driver', ''));
        $backupRoot = (string) config('filesystems.disks.'.$backupDisk.'.root', '');

        if ($deploymentModel !== 'docker-compose') {
            $issues[] = 'deployment-model';
        }
        if ($offsiteRequired && ($backupDisk === $primaryDisk || $backupDisk === 'local')) {
            $issues[] = 'backup-storage-boundary';
        }
        if ($offsiteRequired && $backupDriver === '') {
            $issues[] = 'backup-storage-driver';
        }
        if ($offsiteRequired && $backupDriver === 'local' && ! $this->isExternalLocalBackupPath($backupRoot)) {
            $issues[] = 'backup-storage-boundary';
        }
        if ($offsiteRequired && $offsiteTarget === '') {
            $issues[] = 'backup-offsite-target';
        }
        if ($recoveryKeyReference === '') {
            $issues[] = 'backup-recovery-key-reference';
        }
        if ((int) config('production.backup.rpo_hours', 24) > 24) {
            $issues[] = 'backup-rpo';
        }
        if ((int) config('production.backup.rto_hours', 4) > 4) {
            $issues[] = 'backup-rto';
        }
        if ((int) config('production.backup.retention.daily', 0) < 14
            || (int) config('production.backup.retention.weekly', 0) < 8
            || (int) config('production.backup.retention.monthly', 0) < 12
        ) {
            $issues[] = 'backup-retention';
        }

        if ($this->app->environment('production')) {
            if ((bool) config('app.debug', false)) {
                $issues[] = 'app-debug';
            }
            if (! (bool) config('session.secure', false)) {
                $issues[] = 'secure-session-cookie';
            }
            if ($offsiteRequired && $backupDriver === 'local' && ! $this->hasDedicatedMountBoundary($backupRoot)) {
                $issues[] = 'backup-storage-mount';
            }

            $cipher = new BackupRecoveryCipher;
            if (! $cipher->configured()) {
                $issues[] = 'backup-recovery-key';
            } elseif ($cipher->sharesApplicationKey()) {
                $issues[] = 'backup-key-boundary';
            }

            if ((bool) config('production.backup.allow_legacy_app_key_decryption', true)) {
                $issues[] = 'backup-legacy-app-key';
            }

            $recoveryStore = (string) config('production.recovery_state_store', '');
            if ((string) config('cache.stores.'.$recoveryStore.'.driver', '') !== 'redis') {
                $issues[] = 'recovery-state-store';
            }
        }

        return array_values(array_unique($issues));
    }

    public function satisfied(): bool
    {
        return $this->issues() === [];
    }

    private function isExternalLocalBackupPath(string $path): bool
    {
        $root = $this->normalizeAbsolutePath($path);
        $applicationRoot = $this->normalizeAbsolutePath(base_path());

        if ($root === null || $root === '/' || $applicationRoot === null) {
            return false;
        }

        return $root !== $applicationRoot && ! str_starts_with($root, $applicationRoot.'/');
    }

    private function hasDedicatedMountBoundary(string $path): bool
    {
        $root = $this->normalizeAbsolutePath($path);
        if ($root === null || ! is_readable('/proc/self/mountinfo')) {
            return false;
        }

        $mounts = file('/proc/self/mountinfo', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (! is_array($mounts)) {
            return false;
        }

        foreach ($mounts as $line) {
            $fields = preg_split('/\s+/', $line);
            if (! is_array($fields) || ! isset($fields[4])) {
                continue;
            }

            $mountPoint = strtr($fields[4], [
                '\\040' => ' ',
                '\\011' => "\t",
                '\\012' => "\n",
                '\\134' => '\\',
            ]);
            $mountPoint = $this->normalizeAbsolutePath($mountPoint);
            if ($mountPoint === null || $mountPoint === '/') {
                continue;
            }

            if ($root === $mountPoint || str_starts_with($root, $mountPoint.'/')) {
                return true;
            }
        }

        return false;
    }

    private function normalizeAbsolutePath(string $path): ?string
    {
        $path = str_replace('\\', '/', trim($path));
        if ($path === '' || ! str_starts_with($path, '/')) {
            return null;
        }

        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                if ($segments === []) {
                    return null;
                }
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return '/'.implode('/', $segments);
    }
}
