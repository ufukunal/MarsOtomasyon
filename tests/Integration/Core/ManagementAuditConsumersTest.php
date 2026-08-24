<?php

use App\Foundation\Logging\SensitiveDataRedactor;
use App\Modules\Core\Authorization\AssignRoleToMembership;
use App\Modules\Core\Authorization\GrantPermissionToRole;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\AuditEntry;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('audits security-sensitive user and role updates with before and after snapshots', function (): void {
    $company = managementAuditCompany();
    $actor = managementAuditActor($company);

    $this->actingAs($actor)
        ->withHeader('X-Correlation-ID', 'management-user-create')
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/settings/users', [
            'name' => 'Managed User',
            'email' => 'managed.user@example.test',
            'password' => 'correct-password-123',
            'role_ids' => [],
        ])
        ->assertRedirect();

    $membership = CompanyMembership::query()
        ->where('company_id', $company->getKey())
        ->whereHas('user', fn ($query) => $query->where('email', 'managed.user@example.test'))
        ->firstOrFail();

    $this->actingAs($actor)
        ->withHeader('X-Correlation-ID', 'management-user-update')
        ->withSession(['active_company_id' => $company->getKey()])
        ->put('/settings/users/'.$membership->getKey(), [
            'name' => 'Managed User Updated',
            'email' => 'managed.user.updated@example.test',
            'password' => '',
            'is_active' => '1',
            'role_ids' => [],
        ])
        ->assertRedirect();

    $userAudit = AuditEntry::query()
        ->where('action', AuditAction::UserUpdated->value)
        ->firstOrFail();

    expect($userAudit->correlation_id)->toBe('management-user-update')
        ->and($userAudit->before_state['name'])->toBe('Managed User')
        ->and($userAudit->after_state['name'])->toBe('Managed User Updated')
        ->and($userAudit->before_state['email'])->toBe(SensitiveDataRedactor::REDACTED)
        ->and($userAudit->after_state['email'])->toBe(SensitiveDataRedactor::REDACTED);

    $this->actingAs($actor)
        ->withHeader('X-Correlation-ID', 'management-role-create')
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/settings/roles', [
            'code' => 'managed-role',
            'name' => 'Managed Role',
            'is_active' => '1',
            'permission_keys' => [],
        ])
        ->assertRedirect();

    $role = Role::query()
        ->where('company_id', $company->getKey())
        ->where('code', 'managed-role')
        ->firstOrFail();

    $this->actingAs($actor)
        ->withHeader('X-Correlation-ID', 'management-role-update')
        ->withSession(['active_company_id' => $company->getKey()])
        ->put('/settings/roles/'.$role->getKey(), [
            'code' => 'managed-role-v2',
            'name' => 'Managed Role Updated',
            'is_active' => '1',
            'permission_keys' => [],
        ])
        ->assertRedirect();

    $roleAudit = AuditEntry::query()
        ->where('action', AuditAction::RoleUpdated->value)
        ->firstOrFail();

    expect($roleAudit->correlation_id)->toBe('management-role-update')
        ->and($roleAudit->before_state['code'])->toBe('managed-role')
        ->and($roleAudit->after_state['code'])->toBe('managed-role-v2')
        ->and($roleAudit->before_state['name'])->toBe('Managed Role')
        ->and($roleAudit->after_state['name'])->toBe('Managed Role Updated');
});

function managementAuditCompany(): Company
{
    return Company::query()->create([
        'code' => 'M1-MGMT-AUDIT',
        'name' => 'M1 Management Audit Company',
    ]);
}

function managementAuditActor(Company $company): User
{
    $user = User::query()->create([
        'name' => 'M1 Management Auditor',
        'email' => 'm1-management-auditor@example.test',
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
        'code' => 'management-auditor',
        'name' => 'Management Auditor',
        'is_active' => true,
    ]);

    foreach ([
        PermissionKey::UserView,
        PermissionKey::UserManage,
        PermissionKey::RoleView,
        PermissionKey::RoleManage,
    ] as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }

    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}
