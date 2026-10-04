<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Period;
use App\Models\User;
use App\Support\Auth\CompanyRoleProvisioner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Demo rol/kullanıcı seed işlemi production ortamında çalıştırılamaz.');
        }

        $adminEmail = (string) config('demo.admin_email', 'admin@mars.local');
        $adminPassword = config('demo.admin_password');

        if (! is_string($adminPassword) || mb_strlen($adminPassword) < 12) {
            throw new RuntimeException(
                'Demo admin oluşturmak için DEMO_ADMIN_PASSWORD en az 12 karakter olmalıdır.',
            );
        }

        $admin = User::query()->firstOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'Mars Yönetici',
                'password' => Hash::make($adminPassword),
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
