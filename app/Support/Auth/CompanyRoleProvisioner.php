<?php

namespace App\Support\Auth;

use App\Models\Company;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class CompanyRoleProvisioner
{
    private const SCREENS = [
        'companies',
        'users',
        'roles',
        'periods',
        'audit',
        'print_profiles',
        'company_copy_permissions',
        'locations',
        'units',
        'product_categories',
        'brands',
        'contacts',
        'products',
        'variant_groups',
        'price_lists',
        'imports',
        'stock',
        'transfers',
        'warehouse_slips',
        'stock_counts',
        'quarantine',
        'reservations',
    ];

    private const ACTIONS = [
        'view',
        'create',
        'update',
        'cancel',
    ];

    /**
     * @return array<string, Role>
     */
    public function handle(Company $company): array
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $allScreenPermissions = [];

        foreach (self::SCREENS as $screen) {
            foreach (self::ACTIONS as $action) {
                $allScreenPermissions[] = "{$screen}.{$action}";
            }
        }

        $permissionNames = [
            ...$allScreenPermissions,
            'cost.view',
            'periods.reopen',
            'contacts.sensitive.view',
        ];

        foreach ($permissionNames as $permissionName) {
            Permission::query()->firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        $viewerPermissions = array_values(array_filter(
            $allScreenPermissions,
            fn (string $name): bool => str_ends_with($name, '.view'),
        ));

        $technicalViews = [
            'units.view',
            'product_categories.view',
            'brands.view',
            'variant_groups.view',
        ];

        $roleMatrix = [
            'Yönetici' => $permissionNames,
            'Muhasebe' => [
                'periods.view', 'periods.create', 'periods.update', 'periods.cancel',
                'audit.view',
                'print_profiles.view', 'print_profiles.create', 'print_profiles.update', 'print_profiles.cancel',
                'contacts.view', 'contacts.create', 'contacts.update',
                'products.view', 'locations.view', 'price_lists.view', 'stock.view', 'cost.view',
                ...$technicalViews,
            ],
            'Satış' => [
                'print_profiles.view',
                'contacts.view', 'contacts.create', 'contacts.update',
                'products.view', 'locations.view', 'price_lists.view', 'stock.view',
                ...$technicalViews,
            ],
            'Satınalma' => [
                'print_profiles.view', 'cost.view',
                'contacts.view', 'contacts.create', 'contacts.update',
                'products.view', 'locations.view', 'stock.view',
                ...$technicalViews,
            ],
            'Depo' => [
                'print_profiles.view',
                'contacts.view', 'products.view',
                'locations.view', 'locations.create', 'locations.update', 'stock.view',
                'transfers.view', 'transfers.create', 'transfers.update', 'transfers.cancel',
                'warehouse_slips.view', 'warehouse_slips.create', 'warehouse_slips.update', 'warehouse_slips.cancel',
                'stock_counts.view', 'stock_counts.create', 'stock_counts.update', 'stock_counts.cancel',
                'quarantine.view', 'quarantine.update',
                ...$technicalViews,
            ],
            'Üretim' => [
                'print_profiles.view', 'cost.view',
                'contacts.view', 'products.view', 'locations.view', 'stock.view',
                ...$technicalViews,
            ],
            'Görüntüleyici' => $viewerPermissions,
        ];
        setPermissionsTeamId($company->id);

        $roles = [];

        foreach ($roleMatrix as $roleName => $rolePermissions) {
            $role = Role::query()->firstOrCreate([
                'company_id' => $company->id,
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            $role->syncPermissions($rolePermissions);
            $roles[$roleName] = $role;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $roles;
    }
}
