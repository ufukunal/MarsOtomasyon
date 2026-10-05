<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection('master');
        $now = now();

        $permissionId = $connection->table('permissions')
            ->where('name', 'reports.presets.share')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            $permissionId = $connection->table('permissions')->insertGetId([
                'name' => 'reports.presets.share',
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $roleIds = $connection->table('roles')
            ->whereIn('name', ['Yönetici', 'Muhasebe'])
            ->where('guard_name', 'web')
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            $connection->table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $connection = DB::connection('master');
        $permissionId = $connection->table('permissions')
            ->where('name', 'reports.presets.share')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        $connection->table('role_has_permissions')->where('permission_id', $permissionId)->delete();
        $connection->table('permissions')->where('id', $permissionId)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
