<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\OperationalAlert;
use App\Support\Cache\CacheKey;
use App\Support\Operations\BackupHealthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class OperationsMonitorCommand extends Command
{
    protected $signature = 'operations:monitor';

    protected $description = 'Faz 0 operasyonel uyarı koşullarını kontrol eder';

    public function handle(): int
    {
        $alerts = [];

        $failedJobs = DB::connection('master')->table('failed_jobs')->count();

        if ($failedJobs > 0) {
            $alerts[] = [
                'failed-jobs',
                'Başarısız kuyruk işleri',
                "{$failedJobs} başarısız kuyruk işi var.",
            ];
        }

        $backup = app(BackupHealthService::class)->check();

        if (! $backup['ok']) {
            $alerts[] = [
                'backup-unhealthy',
                'Yedek sağlığı bozuk',
                'Bir veya daha fazla zorunlu yedek hedefi eksik ya da eski.',
            ];
        }

        $total = disk_total_space(base_path());
        $free = disk_free_space(base_path());

        if ($total && $free !== false) {
            $usedPercent = (($total - $free) / $total) * 100;

            if ($usedPercent >= 85) {
                $alerts[] = [
                    'disk-usage',
                    'Disk doluluk uyarısı',
                    sprintf('Disk doluluk oranı %.1f%%.', $usedPercent),
                ];
            }
        }

        foreach ($alerts as [$code, $title, $message]) {
            $lockKey = CacheKey::global("ops-alert:{$code}");

            if (! Cache::store('redis')->add($lockKey, true, now()->addHours(6))) {
                continue;
            }

            $adminIds = DB::connection('master')
                ->table('model_has_roles')
                ->join('role_has_permissions', 'role_has_permissions.role_id', '=', 'model_has_roles.role_id')
                ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                ->where('permissions.name', 'companies.update')
                ->where('permissions.guard_name', 'web')
                ->where('model_has_roles.model_type', User::class)
                ->pluck('model_has_roles.model_id')
                ->unique();

            $admins = User::query()
                ->whereIn('id', $adminIds)
                ->where('is_active', true)
                ->get();

            Notification::send(
                $admins,
                new OperationalAlert($title, $message, $code),
            );
        }

        return self::SUCCESS;
    }
}
