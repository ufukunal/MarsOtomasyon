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

        if ($deploymentModel !== 'docker-compose') {
            $issues[] = 'deployment-model';
        }
        if ($offsiteRequired && ($backupDisk === $primaryDisk || $backupDisk === 'local')) {
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
        if ((int) config('production.backup.retention.daily', 0) < 7
            || (int) config('production.backup.retention.weekly', 0) < 4
            || (int) config('production.backup.retention.monthly', 0) < 6) {
            $issues[] = 'backup-retention';
        }
        if ($this->app->environment('production')) {
            if ((bool) config('app.debug', false)) {
                $issues[] = 'app-debug';
            }
            if (! (bool) config('session.secure', false)) {
                $issues[] = 'secure-session-cookie';
            }
        }

        return $issues;
    }

    public function satisfied(): bool
    {
        return $this->issues() === [];
    }
}
