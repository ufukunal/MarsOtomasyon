<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Period;
use App\Models\User;
use App\Support\Auth\CompanyRoleProvisioner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@mars.local'],
            [
                'name' => 'Mars Yönetici',
                'password' => Hash::make('password'),
                'is_active' => true,
            ],
        );

        if (! $admin->is_active) {
            $admin->forceFill(['is_active' => true])->save();
        }

        foreach (Company::query()->orderBy('id')->get() as $company) {
            $roles = app(CompanyRoleProvisioner::class)->handle($company);

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

            setPermissionsTeamId($company->id);
            $admin->unsetRelation('roles');
            $admin->assignRole($roles['Yönetici']);

            Period::query()
                ->where('company_id', $company->id)
                ->pluck('id')
                ->each(function (int $periodId) use ($admin): void {
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
                });
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
    }
}
