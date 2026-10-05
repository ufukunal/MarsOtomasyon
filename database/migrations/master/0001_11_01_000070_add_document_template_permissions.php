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
        $names = ['document_templates.view', 'document_templates.update'];

        foreach ($names as $name) {
            $permissionId = $connection->table('permissions')
                ->where('name', $name)
                ->where('guard_name', 'web')
                ->value('id');

            if (! $permissionId) {
                $permissionId = $connection->table('permissions')->insertGetId([
                    'name' => $name,
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
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $connection = DB::connection('master');
        $ids = $connection->table('permissions')
            ->whereIn('name', ['document_templates.view', 'document_templates.update'])
            ->where('guard_name', 'web')
            ->pluck('id');

        $connection->table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        $connection->table('permissions')->whereIn('id', $ids)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
