<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection('master');
        $permissionId = $connection->table('permissions')
            ->where('name', 'stock.view')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        $roleIds = $connection->table('roles')
            ->whereIn('name', ['Muhasebe', 'Satış', 'Satınalma', 'Depo', 'Üretim'])
            ->where('guard_name', 'web')
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            $connection->table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }
    }

    public function down(): void
    {
        $connection = DB::connection('master');
        $permissionId = $connection->table('permissions')
            ->where('name', 'stock.view')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        $roleIds = $connection->table('roles')
            ->whereIn('name', ['Muhasebe', 'Satış', 'Satınalma', 'Depo', 'Üretim'])
            ->where('guard_name', 'web')
            ->pluck('id');

        $connection->table('role_has_permissions')
            ->where('permission_id', $permissionId)
            ->whereIn('role_id', $roleIds)
            ->delete();
    }
};
