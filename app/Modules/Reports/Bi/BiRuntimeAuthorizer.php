<?php

namespace App\Modules\Reports\Bi;

use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\User;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

final class BiRuntimeAuthorizer
{
    public function allows(int $companyId, int $userId, bool $includePii): bool
    {
        $user = User::query()->find($userId);
        if (! $user instanceof User || UserStatus::tryFrom((string) $user->getRawOriginal('status')) !== UserStatus::Active) {
            return false;
        }

        if (! $this->hasPermission($companyId, $userId, PermissionKey::ReportsBiExport)) {
            return false;
        }

        return ! $includePii || $this->hasPermission($companyId, $userId, PermissionKey::ReportsBiPii);
    }

    private function hasPermission(int $companyId, int $userId, PermissionKey $permission): bool
    {
        return DB::table('company_memberships as memberships')
            ->join('company_membership_roles as assignments', function (JoinClause $join): void {
                $join->on('assignments.membership_id', '=', 'memberships.id')
                    ->on('assignments.company_id', '=', 'memberships.company_id');
            })
            ->join('roles', function (JoinClause $join): void {
                $join->on('roles.id', '=', 'assignments.role_id')
                    ->on('roles.company_id', '=', 'assignments.company_id');
            })
            ->join('role_permissions', 'role_permissions.role_id', '=', 'roles.id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('memberships.company_id', $companyId)
            ->where('memberships.user_id', $userId)
            ->where('memberships.is_active', true)
            ->where('roles.is_active', true)
            ->where('permissions.key', $permission->value)
            ->exists();
    }
}
