<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use RuntimeException;

final class BootstrapAdminCommand extends Command
{
    protected $signature = 'mars:bootstrap-admin
        {email : First platform administrator email}
        {--name= : Administrator display name}
        {--company-code=MARS : Initial company code}
        {--company-name=Mars : Initial company name}
        {--branch-code=MERKEZ : Initial branch code}
        {--branch-name=Merkez : Initial branch name}';

    protected $description = 'Create or complete the first platform administrator, company, branch and full company role';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('A valid administrator email is required.');
        }

        $name = trim((string) ($this->option('name') ?: 'Mars Administrator'));
        $companyCode = mb_strtoupper(trim((string) $this->option('company-code')));
        $companyName = trim((string) $this->option('company-name'));
        $branchCode = mb_strtoupper(trim((string) $this->option('branch-code')));
        $branchName = trim((string) $this->option('branch-name'));

        foreach ([$name, $companyCode, $companyName, $branchCode, $branchName] as $value) {
            if ($value === '') {
                throw new InvalidArgumentException('Bootstrap names and codes cannot be empty.');
            }
        }

        $existingUser = DB::table('users')->whereRaw('lower(email) = ?', [$email])->first();
        if ($existingUser === null && DB::table('users')->exists()) {
            throw new RuntimeException('Bootstrap creation is only allowed when no users exist. Use the existing administration flow for additional users.');
        }

        $passwordHash = null;
        if ($existingUser === null) {
            $password = (string) $this->secret('Administrator password (minimum 12 characters)');
            if (mb_strlen($password) < 12) {
                throw new InvalidArgumentException('Administrator password must contain at least 12 characters.');
            }
            $confirmation = (string) $this->secret('Confirm administrator password');
            if (! hash_equals($password, $confirmation)) {
                throw new InvalidArgumentException('Administrator password confirmation does not match.');
            }
            $passwordHash = Hash::make($password);
        }

        DB::transaction(function () use ($email, $name, $companyCode, $companyName, $branchCode, $branchName, $existingUser, $passwordHash): void {
            $now = now();

            if ($existingUser === null) {
                $userId = DB::table('users')->insertGetId([
                    'name' => $name,
                    'email' => $email,
                    'password' => $passwordHash,
                    'status' => 'active',
                    'is_platform_admin' => true,
                    'email_verified_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $userId = (int) $existingUser->id;
                DB::table('users')->where('id', $userId)->update([
                    'status' => 'active',
                    'is_platform_admin' => true,
                    'updated_at' => $now,
                ]);
            }

            $company = DB::table('companies')->whereRaw('lower(code) = ?', [mb_strtolower($companyCode)])->first();
            $companyId = $company === null
                ? DB::table('companies')->insertGetId([
                    'code' => $companyCode,
                    'name' => $companyName,
                    'status' => 'active',
                    'base_currency_code' => 'TRY',
                    'timezone' => 'Europe/Istanbul',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                : (int) $company->id;

            $branch = DB::table('branches')
                ->where('company_id', $companyId)
                ->whereRaw('lower(code) = ?', [mb_strtolower($branchCode)])
                ->first();
            if ($branch === null) {
                DB::table('branches')->insert([
                    'company_id' => $companyId,
                    'code' => $branchCode,
                    'name' => $branchName,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('branches')->where('id', $branch->id)->update(['is_active' => true, 'updated_at' => $now]);
            }

            $membership = DB::table('company_memberships')
                ->where('company_id', $companyId)
                ->where('user_id', $userId)
                ->first();
            if ($membership === null) {
                $membershipId = DB::table('company_memberships')->insertGetId([
                    'company_id' => $companyId,
                    'user_id' => $userId,
                    'is_active' => true,
                    'joined_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $membershipId = (int) $membership->id;
                DB::table('company_memberships')->where('id', $membershipId)->update([
                    'is_active' => true,
                    'joined_at' => $membership->joined_at ?? $now,
                    'updated_at' => $now,
                ]);
            }

            $role = DB::table('roles')
                ->where('company_id', $companyId)
                ->whereRaw('lower(code) = ?', ['platform_admin'])
                ->first();
            if ($role === null) {
                $roleId = DB::table('roles')->insertGetId([
                    'company_id' => $companyId,
                    'code' => 'PLATFORM_ADMIN',
                    'name' => 'Platform Administrator',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $roleId = (int) $role->id;
                DB::table('roles')->where('id', $roleId)->update(['is_active' => true, 'updated_at' => $now]);
            }

            $permissionIds = DB::table('permissions')->pluck('id');
            foreach ($permissionIds as $permissionId) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => (int) $permissionId,
                ]);
            }

            DB::table('company_membership_roles')->insertOrIgnore([
                'company_id' => $companyId,
                'membership_id' => $membershipId,
                'role_id' => $roleId,
                'assigned_at' => $now,
            ]);
        });

        $this->info('Initial platform administrator and active company context are ready.');

        return self::SUCCESS;
    }
}
