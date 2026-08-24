<?php

namespace App\Modules\Core\Authorization;

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\User;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

final readonly class CompanyPermissionAuthorizer
{
    public function __construct(private ActiveCompanyContext $companyContext) {}

    public function allows(User $user, PermissionKey $permission): bool
    {
        if ($user->status !== UserStatus::Active) {
            return false;
        }

        $companyId = $this->companyContext->id();
        $userId = $user->getKey();

        if ($companyId === null || ! is_int($userId)) {
            return false;
        }

        return DB::table('company_memberships as memberships')
            ->join('company_membership_roles as assignments', function (JoinClause $join): void {
                $join
                    ->on('assignments.membership_id', '=', 'memberships.id')
                    ->on('assignments.company_id', '=', 'memberships.company_id');
            })
            ->join('roles', function (JoinClause $join): void {
                $join
                    ->on('roles.id', '=', 'assignments.role_id')
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
