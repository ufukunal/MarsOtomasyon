<?php

namespace App\Modules\Core\Authorization;

use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Models\Role;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

final class PrivilegeGrantGuard
{
    /** @param Collection<int, Role> $roles */
    public function assertCanGrantRoles(Collection $roles): void
    {
        foreach ($roles as $role) {
            foreach ($role->permissions as $permission) {
                $key = PermissionKey::tryFrom((string) $permission->key);
                abort_if($key === null || ! Gate::allows($key->value), 403, 'Sahip olmadığınız bir yetkiyi atayamazsınız.');
            }
        }
    }

    /** @param list<string> $permissionKeys */
    public function assertCanGrantPermissionKeys(array $permissionKeys): void
    {
        foreach ($permissionKeys as $permissionKey) {
            $key = PermissionKey::tryFrom($permissionKey);
            abort_if($key === null || ! Gate::allows($key->value), 403, 'Sahip olmadığınız bir yetkiyi atayamazsınız.');
        }
    }

    /** @return list<PermissionKey> */
    public function grantablePermissions(): array
    {
        return array_values(array_filter(
            PermissionKey::cases(),
            fn (PermissionKey $permission): bool => Gate::allows($permission->value),
        ));
    }
}
