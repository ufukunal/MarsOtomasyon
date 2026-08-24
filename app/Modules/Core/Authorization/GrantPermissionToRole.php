<?php

namespace App\Modules\Core\Authorization;

use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use DomainException;
use Illuminate\Support\Facades\DB;
use LogicException;

final class GrantPermissionToRole
{
    public function handle(Role $role, PermissionKey $permissionKey): void
    {
        $roleId = $role->getKey();

        if (! is_int($roleId)) {
            throw new LogicException('Permission grant requires a persisted role record.');
        }

        if (! $role->is_active) {
            throw new DomainException('Pasif role yetki verilemez.');
        }

        $permission = Permission::query()
            ->where('key', $permissionKey->value)
            ->firstOrFail();

        $permissionId = $permission->getKey();

        if (! is_int($permissionId)) {
            throw new LogicException('Permission catalog record is not persisted.');
        }

        DB::table('role_permissions')->insertOrIgnore([
            'role_id' => $roleId,
            'permission_id' => $permissionId,
        ]);
    }

    public function revoke(Role $role, PermissionKey $permissionKey): void
    {
        $roleId = $role->getKey();

        if (! is_int($roleId)) {
            return;
        }

        $permissionId = Permission::query()
            ->where('key', $permissionKey->value)
            ->value('id');

        if (! is_int($permissionId)) {
            return;
        }

        DB::table('role_permissions')
            ->where('role_id', $roleId)
            ->where('permission_id', $permissionId)
            ->delete();
    }
}
