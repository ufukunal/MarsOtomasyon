<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Period;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    private const SCREENS = [
        'companies',
        'users',
        'roles',
        'periods',
        'audit',
        'print_profiles',
        'company_copy_permissions',
    ];

    private const ACTIONS = [
        'view',
        'create',
        'update',
        'cancel',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $allScreenPermissions = [];

        foreach (self::SCREENS as $screen) {
            foreach (self::ACTIONS as $action) {
                $allScreenPermissions[] = "{$screen}.{$action}";
            }
        }

        $permissionNames = [...$allScreenPermissions, 'cost.view', 'periods.reopen'];

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

        $roleMatrix = [
            'Yönetici' => $permissionNames,
            'Muhasebe' => [
                'periods.view',
                'periods.create',
                'periods.update',
                'periods.cancel',
                'audit.view',
                'print_profiles.view',
                'print_profiles.create',
                'print_profiles.update',
                'print_profiles.cancel',
                'cost.view',
            ],
            'Satış' => ['print_profiles.view'],
            'Satınalma' => ['print_profiles.view', 'cost.view'],
            'Depo' => ['print_profiles.view'],
            'Üretim' => ['print_profiles.view', 'cost.view'],
            'Görüntüleyici' => $viewerPermissions,
        ];

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@mars.local'],
            [
                'name' => 'Mars Yönetici',
                'password' => Hash::make('password'),
                'is_active' => true,
            ],
        );

        foreach (Company::query()->orderBy('id')->get() as $company) {
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

            DB::connection('master')->table('company_user')->updateOrInsert(
                [
                    'company_id' => $company->id,
                    'user_id' => $admin->id,
                ],
                [
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            $admin->unsetRelation('roles');
            $admin->assignRole($roles['Yönetici']);

            $periodIds = Period::query()
                ->where('company_id', $company->id)
                ->pluck('id');

            foreach ($periodIds as $periodId) {
                DB::connection('master')->table('period_user_access')->updateOrInsert(
                    [
                        'period_id' => $periodId,
                        'user_id' => $admin->id,
                    ],
                    [
                        'is_active' => true,
                        'permission_overrides' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        }

        $firstCompany = Company::query()->orderBy('id')->first();
        $firstPeriod = $firstCompany
            ? Period::query()->where('company_id', $firstCompany->id)->orderByDesc('year')->first()
            : null;

        if ($firstCompany && $firstPeriod) {
            $admin->forceFill([
                'last_company_id' => $firstCompany->id,
                'last_period_id' => $firstPeriod->id,
            ])->save();
        }

        setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
