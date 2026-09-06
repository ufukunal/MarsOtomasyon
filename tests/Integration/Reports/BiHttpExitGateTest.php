<?php

use App\Modules\Core\Authorization\AssignRoleToMembership;
use App\Modules\Core\Authorization\GrantPermissionToRole;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('serves the BI workspace and persists an authorized schedule', function (): void {
    [$company, $branch, $user] = m31BiActor([PermissionKey::ReportsBiExport], 'workspace');

    $this->actingAs($user)
        ->withSession(['active_company_id' => $company->getKey(), 'active_branch_id' => $branch->getKey()])
        ->get('/reports/bi')
        ->assertOk()
        ->assertSee('BI Dışa Aktarım')
        ->assertSee('sales_invoices')
        ->assertSee('account_aging');

    $this->actingAs($user)
        ->withSession(['active_company_id' => $company->getKey(), 'active_branch_id' => $branch->getKey()])
        ->post('/reports/bi/schedules', [
            'schedule_key' => 'daily-sales',
            'dataset_key' => 'sales_invoices',
            'format' => 'json',
            'fields' => ['number', 'gross_total'],
            'interval_minutes' => 60,
            'next_run_at' => now()->addHour()->format('Y-m-d H:i:s'),
        ])
        ->assertRedirect(route('reports.bi.index'));

    expect(DB::table('bi_export_schedules')->where('company_id', $company->getKey())->where('schedule_key', 'daily-sales')->exists())->toBeTrue();
});

it('separates BI export permission, PII permission and feature availability', function (): void {
    [$company, $branch, $exporter] = m31BiActor([PermissionKey::ReportsBiExport], 'exporter');
    [, , $denied] = m31BiActor([], 'denied', $company, $branch);

    $this->actingAs($denied)
        ->withSession(['active_company_id' => $company->getKey(), 'active_branch_id' => $branch->getKey()])
        ->get('/reports/bi')
        ->assertForbidden();

    $this->actingAs($exporter)
        ->withSession(['active_company_id' => $company->getKey(), 'active_branch_id' => $branch->getKey()])
        ->post('/reports/bi/sales_invoices/export', [
            'format' => 'json',
            'fields' => ['customer_legal_name'],
            'include_pii' => true,
        ])
        ->assertForbidden();

    config(['mars.features.bi_exports' => false]);
    $this->actingAs($exporter)
        ->withSession(['active_company_id' => $company->getKey(), 'active_branch_id' => $branch->getKey()])
        ->get('/reports/bi')
        ->assertNotFound();
});

/**
 * @param  list<PermissionKey>  $permissions
 * @return array{Company,Branch,User}
 */
function m31BiActor(array $permissions, string $suffix, ?Company $company = null, ?Branch $branch = null): array
{
    $company ??= Company::query()->create(['code' => 'M31-'.strtoupper($suffix), 'name' => 'M31 '.$suffix]);
    $branch ??= Branch::query()->create(['company_id' => $company->getKey(), 'code' => 'HQ', 'name' => 'Merkez', 'is_active' => true]);
    $user = User::query()->create([
        'name' => 'M31 '.$suffix,
        'email' => 'm31-'.$suffix.'@example.test',
        'password' => 'correct-password',
        'status' => UserStatus::Active,
    ]);
    $membership = CompanyMembership::query()->create([
        'company_id' => $company->getKey(),
        'user_id' => $user->getKey(),
        'is_active' => true,
        'joined_at' => now(),
    ]);
    $role = Role::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'm31-'.$suffix,
        'name' => 'M31 '.$suffix,
        'is_active' => true,
    ]);
    foreach ($permissions as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return [$company, $branch, $user];
}
