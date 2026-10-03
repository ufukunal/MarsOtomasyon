<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SCREENS = [
        'locations',
        'units',
        'product_categories',
        'brands',
        'contacts',
        'products',
        'variant_groups',
        'price_lists',
        'imports',
    ];

    public function up(): void
    {
        $connection = DB::connection('master');
        $permissionIds = [];

        foreach (self::SCREENS as $screen) {
            foreach (['view', 'create', 'update', 'cancel'] as $action) {
                $name = "{$screen}.{$action}";

                $connection->table('permissions')->updateOrInsert(
                    ['name' => $name, 'guard_name' => 'web'],
                    ['updated_at' => now(), 'created_at' => now()],
                );

                $permissionIds[$name] = (int) $connection->table('permissions')
                    ->where('name', $name)
                    ->where('guard_name', 'web')
                    ->value('id');
            }
        }

        $sensitive = 'contacts.sensitive.view';

        $connection->table('permissions')->updateOrInsert(
            ['name' => $sensitive, 'guard_name' => 'web'],
            ['updated_at' => now(), 'created_at' => now()],
        );

        $permissionIds[$sensitive] = (int) $connection->table('permissions')
            ->where('name', $sensitive)
            ->where('guard_name', 'web')
            ->value('id');

        $adminRoleIds = $connection->table('roles')
            ->where('name', 'Yönetici')
            ->where('guard_name', 'web')
            ->pluck('id');

        foreach ($adminRoleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                $connection->table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        $viewerPermissionIds = collect($permissionIds)
            ->filter(fn (int $id, string $name): bool => str_ends_with($name, '.view') && $name !== $sensitive)
            ->values();

        $viewerRoleIds = $connection->table('roles')
            ->where('name', 'Görüntüleyici')
            ->where('guard_name', 'web')
            ->pluck('id');

        foreach ($viewerRoleIds as $roleId) {
            foreach ($viewerPermissionIds as $permissionId) {
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

        $names = collect(self::SCREENS)
            ->flatMap(fn (string $screen): array => array_map(
                fn (string $action): string => "{$screen}.{$action}",
                ['view', 'create', 'update', 'cancel'],
            ))
            ->push('contacts.sensitive.view');

        $permissionIds = $connection->table('permissions')
            ->whereIn('name', $names)
            ->where('guard_name', 'web')
            ->pluck('id');

        $roleIds = $connection->table('roles')
            ->whereIn('name', ['Yönetici', 'Görüntüleyici'])
            ->where('guard_name', 'web')
            ->pluck('id');

        // Permission kayıtları silinmez; rollback sonrasında yapılmış özel atamalar korunur.
        $connection->table('role_has_permissions')
            ->whereIn('role_id', $roleIds)
            ->whereIn('permission_id', $permissionIds)
            ->delete();
    }
};
