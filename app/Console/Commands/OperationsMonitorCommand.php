<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\OperationalAlert;
use App\Support\Cache\CacheKey;
use App\Support\Operations\OperationalHealthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class OperationsMonitorCommand extends Command
{
    protected $signature = 'operations:monitor';
    protected $description = 'Operational health sonuçlarından alarm üretir';

    public function handle(OperationalHealthService $health): int
    {
        $result = $health->check();
        $alerts = [];

        foreach ($result['checks'] as $name => $check) {
            if ((bool) ($check['ok'] ?? false)) {
                continue;
            }

            $alerts[] = [
                'health-'.$name,
                'Operational health uyarısı: '.$name,
                sprintf(
                    '%s kontrolü %s durumunda. correlation_id=%s',
                    $name,
                    (string) ($check['status'] ?? 'unknown'),
                    $result['correlation_id'],
                ),
            ];
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

            Notification::send($admins, new OperationalAlert($title, $message, $code));
        }

        return $result['status'] === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
