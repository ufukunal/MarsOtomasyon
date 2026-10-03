<?php

namespace Tests\Support;

use App\Models\Company;
use App\Models\Period;
use App\Models\User;
use App\Support\Auth\CompanyRoleProvisioner;
use App\Support\Company\CompanyContext;
use App\Support\Period\PeriodContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

trait CreatesPeriodDatabases
{
    /** @var array<int, string> */
    private array $createdPeriodDatabases = [];

    /**
     * @return array{0:Company,1:Period}
     */
    protected function createCompanyWithPeriod(
        string $code = 'ABC',
        int $year = 2026,
        string $status = 'active',
    ): array {
        $suffix = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $prefix = 'TST_'.$code.'_'.$suffix;
        $database = $prefix.'_'.$year;

        $company = Company::factory()->create([
            'code' => substr(strtoupper($code.'_'.$suffix), 0, 20),
            'name' => "{$code} Test",
            'db_prefix' => substr($prefix, 0, 30),
        ]);

        DB::connection('master')->statement(sprintf('CREATE DATABASE "%s"', $database));
        $this->createdPeriodDatabases[] = $database;

        $period = Period::factory()->create([
            'company_id' => $company->id,
            'year' => $year,
            'database_name' => $database,
            'starts_on' => "{$year}-01-01",
            'ends_on' => "{$year}-12-31",
            'status' => $status,
        ]);

        PeriodContext::useSystem($company->id, $period->id);

        $exit = Artisan::call('migrate', [
            '--database' => 'period',
            '--path' => 'database/migrations/period',
            '--force' => true,
        ]);

        if ($exit !== 0) {
            throw new RuntimeException("Period migration başarısız: {$database}");
        }

        app(\App\Actions\ReferenceData\SeedPeriodReferenceData::class)->handle();

        return [$company, $period];
    }

    protected function createUserWithPeriodAccess(
        Company $company,
        Period $period,
        string $role = 'Yönetici',
    ): User {
        $user = User::factory()->create([
            'last_company_id' => $company->id,
            'last_period_id' => $period->id,
        ]);

        DB::connection('master')->table('company_user')->insert([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('master')->table('period_user_access')->insert([
            'period_id' => $period->id,
            'user_id' => $user->id,
            'is_active' => true,
            'permission_overrides' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roles = app(CompanyRoleProvisioner::class)->handle($company);
        CompanyContext::use($company->id);
        $user->unsetRelation('roles');
        $user->assignRole($roles[$role]);
        CompanyContext::clear();

        return $user->refresh();
    }

    protected function loginToPeriod(User $user, Company $company, Period $period): void
    {
        $this->actingAs($user);
        PeriodContext::use($company->id, $period->id);
    }

    protected function dropCreatedPeriodDatabases(): void
    {
        PeriodContext::clear();

        config(['database.connections.period_source.database' => null]);
        DB::purge('period_source');

        foreach (array_reverse($this->createdPeriodDatabases) as $database) {
            if (! preg_match('/^TST_[A-Za-z0-9_]+_\d{4}$/D', $database)) {
                throw new RuntimeException("Güvenli olmayan test period DB adı: {$database}");
            }

            DB::connection('master')->statement(
                sprintf('DROP DATABASE IF EXISTS "%s" WITH (FORCE)', $database),
            );
        }

        $this->createdPeriodDatabases = [];
    }
}
