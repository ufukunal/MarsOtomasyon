<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection('master');
        $roleIds = $connection->table('roles')->where('name', 'Depo')->where('guard_name', 'web')->pluck('id');
        $permissionIds = $connection->table('permissions')
            ->whereIn('name', ['warehouse_slips.view', 'warehouse_slips.create', 'warehouse_slips.update', 'warehouse_slips.cancel'])
            ->where('guard_name', 'web')
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                $connection->table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $connection = DB::connection('master');
        $roleIds = $connection->table('roles')->where('name', 'Depo')->where('guard_name', 'web')->pluck('id');
        $permissionIds = $connection->table('permissions')
            ->whereIn('name', ['warehouse_slips.view', 'warehouse_slips.create', 'warehouse_slips.update', 'warehouse_slips.cancel'])
            ->where('guard_name', 'web')
            ->pluck('id');

        $connection->table('role_has_permissions')->whereIn('role_id', $roleIds)->whereIn('permission_id', $permissionIds)->delete();
    }
};
