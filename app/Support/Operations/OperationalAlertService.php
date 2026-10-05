<?php

namespace App\Support\Operations;

use App\Models\User;
use App\Notifications\OperationalAlert;
use App\Support\Cache\CacheKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

final class OperationalAlertService
{
    public function send(
        string $code,
        string $title,
        string $message,
        int $dedupHours = 6,
    ): void {
        try {
            $lockKey = CacheKey::global('ops-alert:'.$code);

            if (! Cache::store('redis')->add($lockKey, true, now()->addHours($dedupHours))) {
                return;
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
        } catch (Throwable $exception) {
            Log::warning('Operational alert gönderilemedi.', [
                'alert_code' => $code,
                'exception_class' => $exception::class,
            ]);
        }
    }
}
