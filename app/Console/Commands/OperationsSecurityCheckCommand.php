<?php

namespace App\Console\Commands;

use App\Support\Operations\DatabasePrivilegeVerifier;
use Illuminate\Console\Command;

class OperationsSecurityCheckCommand extends Command
{
    protected $signature = 'operations:security-check';
    protected $description = 'Production secret/config/file-permission güvenlik kontrollerini çalıştırır';

    public function handle(DatabasePrivilegeVerifier $privileges): int
    {
        $failures = $privileges->failures();

        if (app()->environment('production') && (bool) config('app.debug')) {
            $failures[] = 'APP_DEBUG production ortamında false olmalıdır.';
        }

        if (app()->environment('production') && trim((string) config('app.key')) === '') {
            $failures[] = 'APP_KEY production ortamında zorunludur.';
        }

        if (app()->environment('production') && trim((string) config('backup.backup.password')) === '') {
            $failures[] = 'BACKUP_ARCHIVE_PASSWORD production ortamında zorunludur.';
        }

        if (app()->environment('production')) {
            foreach (['key', 'secret', 'bucket'] as $field) {
                if (trim((string) config("filesystems.disks.backup_external.{$field}")) === '') {
                    $failures[] = "backup_external {$field} production ortamında zorunludur.";
                }
            }
        }

        $envFile = base_path('.env');
        if (app()->environment('production') && is_file($envFile)) {
            $mode = fileperms($envFile);
            if ($mode !== false && (($mode & 0007) !== 0 || ($mode & 0020) !== 0)) {
                $failures[] = '.env group/world writable/readable olmamalıdır; önerilen mod 0640 veya daha sıkıdır.';
            }
        }

        if ($failures !== []) {
            foreach ($failures as $failure) {
                $this->error($failure);
            }

            return self::FAILURE;
        }

        $this->info('Production security configuration kontrolleri başarılı.');

        return self::SUCCESS;
    }
}
