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
use App\Modules\Reports\Bi\AccountAgingDataset;
use App\Modules\Reports\Bi\BiScheduleRunner;
use App\Modules\Reports\ReportService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class);

it('reauthorizes a scheduled BI export at runtime and records revocation failures', function (): void {
    $company = Company::query()->create(['code' => 'M31-SCHED', 'name' => 'M31 Scheduled']);
    $branch = Branch::query()->create(['company_id' => $company->getKey(), 'code' => 'HQ', 'name' => 'Merkez', 'is_active' => true]);
    $user = User::query()->create([
        'name' => 'BI Runner',
        'email' => 'm31-bi-runner@example.test',
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
        'code' => 'bi-exporter',
        'name' => 'BI Exporter',
        'is_active' => true,
    ]);
    app(GrantPermissionToRole::class)->handle($role, PermissionKey::ReportsBiExport);
    app(AssignRoleToMembership::class)->handle($membership, $role);

    $scheduleId = (int) DB::table('bi_export_schedules')->insertGetId([
        'company_id' => $company->getKey(),
        'branch_id' => $branch->getKey(),
        'created_by_user_id' => $user->getKey(),
        'dataset_key' => 'sales_invoices',
        'schema_version' => 1,
        'format' => 'json',
        'fields' => json_encode(['number'], JSON_THROW_ON_ERROR),
        'include_pii' => false,
        'schedule_key' => 'daily-sales',
        'interval_minutes' => 5,
        'is_enabled' => true,
        'next_run_at' => now()->subMinute(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $first = app(BiScheduleRunner::class)->runDue();
    expect($first)->toBe(['succeeded' => 1, 'failed' => 0, 'skipped' => 0])
        ->and((string) DB::table('bi_export_runs')->where('schedule_id', $scheduleId)->latest('id')->value('status'))->toBe('succeeded');

    $role->forceFill(['is_active' => false])->save();
    DB::table('bi_export_schedules')->where('id', $scheduleId)->update(['next_run_at' => now()->subMinute()]);

    $second = app(BiScheduleRunner::class)->runDue();
    expect($second)->toBe(['succeeded' => 0, 'failed' => 1, 'skipped' => 0])
        ->and((string) DB::table('bi_export_runs')->where('schedule_id', $scheduleId)->latest('id')->value('status'))->toBe('failed')
        ->and((string) DB::table('bi_export_schedules')->where('id', $scheduleId)->value('last_error'))->toContain('authorization');
});

it('builds account aging BI rows from the authoritative report service', function (): void {
    $company = Company::query()->create(['code' => 'M31-AGING', 'name' => 'M31 Aging']);
    $reports = app(ReportService::class);
    $dataset = new AccountAgingDataset($reports);

    $authoritative = $reports->build((int) $company->getKey(), [
        'as_of' => now()->toDateString(),
        'currency' => null,
        'warehouse_id' => null,
        'account_type' => null,
    ]);

    expect(iterator_to_array($dataset->rows((int) $company->getKey())))->toBe([])
        ->and($authoritative['aging'])->toBe([]);
});
