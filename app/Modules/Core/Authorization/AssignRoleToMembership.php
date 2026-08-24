<?php

namespace App\Modules\Core\Authorization;

use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\Role;
use DomainException;
use Illuminate\Support\Facades\DB;
use LogicException;

final class AssignRoleToMembership
{
    public function handle(CompanyMembership $membership, Role $role): void
    {
        $membershipId = $membership->getKey();
        $roleId = $role->getKey();

        if (! is_int($membershipId) || ! is_int($roleId)) {
            throw new LogicException('Role assignment requires persisted membership and role records.');
        }

        if (! $membership->is_active) {
            throw new DomainException('Pasif şirket üyeliğine rol atanamaz.');
        }

        if (! $role->is_active) {
            throw new DomainException('Pasif rol kullanıcıya atanamaz.');
        }

        if ($membership->company_id !== $role->company_id) {
            throw new DomainException('Rol ve şirket üyeliği aynı şirkete ait olmalıdır.');
        }

        DB::table('company_membership_roles')->insertOrIgnore([
            'company_id' => $membership->company_id,
            'membership_id' => $membershipId,
            'role_id' => $roleId,
        ]);
    }

    public function revoke(CompanyMembership $membership, Role $role): void
    {
        $membershipId = $membership->getKey();
        $roleId = $role->getKey();

        if (! is_int($membershipId) || ! is_int($roleId)) {
            return;
        }

        DB::table('company_membership_roles')
            ->where('membership_id', $membershipId)
            ->where('role_id', $roleId)
            ->delete();
    }
}
