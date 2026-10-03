<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection('master');
        $technicalViews = ['units.view','product_categories.view','brands.view','variant_groups.view'];

        $matrix = [
            'Muhasebe' => ['contacts.view','contacts.create','contacts.update','products.view','locations.view','price_lists.view', ...$technicalViews],
            'Satış' => ['contacts.view','contacts.create','contacts.update','products.view','locations.view','price_lists.view', ...$technicalViews],
            'Satınalma' => ['contacts.view','contacts.create','contacts.update','products.view','locations.view', ...$technicalViews],
            'Depo' => ['contacts.view','products.view','locations.view','locations.create','locations.update', ...$technicalViews],
            'Üretim' => ['contacts.view','products.view','locations.view', ...$technicalViews],
        ];

        foreach ($matrix as $roleName => $permissions) {
            $roleIds = $connection->table('roles')->where('name', $roleName)->where('guard_name', 'web')->pluck('id');
            $permissionIds = $connection->table('permissions')->whereIn('name', $permissions)->where('guard_name', 'web')->pluck('id');

            foreach ($roleIds as $roleId) {
                foreach ($permissionIds as $permissionId) {
                    $connection->table('role_has_permissions')->insertOrIgnore([
                        'permission_id' => $permissionId,
                        'role_id' => $roleId,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $connection = DB::connection('master');
        $roleIds = $connection->table('roles')
            ->whereIn('name', ['Muhasebe','Satış','Satınalma','Depo','Üretim'])
            ->where('guard_name', 'web')
            ->pluck('id');

        $permissionIds = $connection->table('permissions')
            ->whereIn('name', [
                'contacts.view','contacts.create','contacts.update','products.view',
                'locations.view','locations.create','locations.update','price_lists.view',
                'units.view','product_categories.view','brands.view','variant_groups.view',
            ])
            ->where('guard_name', 'web')
            ->pluck('id');

        $connection->table('role_has_permissions')
            ->whereIn('role_id', $roleIds)
            ->whereIn('permission_id', $permissionIds)
            ->delete();
    }
};
