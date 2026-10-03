<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\OperationalAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

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

        $files = Storage::disk('backups')->allFiles();

        if ($files === []) {
            $alerts[] = ['backup-missing', 'Yedek bulunamadı', 'Yerel backup diskinde yedek bulunamadı.'];
        } else {
            $latest = collect($files)
                ->map(fn (string $file): int => Storage::disk('backups')->lastModified($file))
                ->max();

            if ((now()->timestamp - (int) $latest) > 36 * 3600) {
                $alerts[] = ['backup-stale', 'Yedek eski', 'Son yerel yedek 36 saatten daha eski.'];
            }
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
            $lockKey = "g:ops-alert:{$code}";

            if (! Cache::store('redis')->add($lockKey, true, now()->addHours(6))) {
                continue;
            }

            $adminIds = DB::connection('master')
                ->table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('roles.name', 'Yönetici')
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
