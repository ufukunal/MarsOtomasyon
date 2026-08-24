<?php

use App\Modules\Core\Authorization\AssignRoleToMembership;
use App\Modules\Core\Authorization\GrantPermissionToRole;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('keeps the database permission catalog aligned with the M1 permission enum', function (): void {
    $expected = array_map(
        static fn (PermissionKey $permission): string => $permission->value,
        PermissionKey::cases(),
    );
    sort($expected);

    $actual = Permission::query()
        ->orderBy('key')
        ->pluck('key')
        ->map(static fn (mixed $key): string => (string) $key)
        ->all();

    expect($actual)->toBe($expected);
});

it('allows only permissions granted through an active role in the active company', function (): void {
    $company = authorizationCompany('MARS');
    $user = authorizationUser('allowed@example.test');
    $membership = authorizationMembership($company, $user);
    $role = authorizationRole($company, 'operator');

    app(GrantPermissionToRole::class)->handle($role, PermissionKey::RoleView);
    app(AssignRoleToMembership::class)->handle($membership, $role);
    app(ActiveCompanyContext::class)->set($company);

    expect($user->can(PermissionKey::RoleView->value))->toBeTrue()
        ->and($user->can(PermissionKey::RoleManage->value))->toBeFalse()
        ->and($user->can('core.unknown.permission'))->toBeFalse();
});

it('denies permissions when active company context is missing', function (): void {
    $company = authorizationCompany('MARS');
    $user = authorizationUser('no-context@example.test');
    $membership = authorizationMembership($company, $user);
    $role = authorizationRole($company, 'viewer');

    app(GrantPermissionToRole::class)->handle($role, PermissionKey::CompanyView);
    app(AssignRoleToMembership::class)->handle($membership, $role);

    expect($user->can(PermissionKey::CompanyView->value))->toBeFalse();
});

it('denies permissions for an inactive company membership', function (): void {
    $company = authorizationCompany('MARS');
    $user = authorizationUser('inactive-membership@example.test');
    $membership = authorizationMembership($company, $user);
    $role = authorizationRole($company, 'viewer');

    app(GrantPermissionToRole::class)->handle($role, PermissionKey::CompanyView);
    app(AssignRoleToMembership::class)->handle($membership, $role);

    $membership->forceFill(['is_active' => false])->save();
    app(ActiveCompanyContext::class)->set($company);

    expect($user->can(PermissionKey::CompanyView->value))->toBeFalse();
});

it('denies permissions for an inactive internal user', function (): void {
    $company = authorizationCompany('MARS');
    $user = authorizationUser('inactive-user@example.test');
    $membership = authorizationMembership($company, $user);
    $role = authorizationRole($company, 'viewer');

    app(GrantPermissionToRole::class)->handle($role, PermissionKey::CompanyView);
    app(AssignRoleToMembership::class)->handle($membership, $role);

    $user->forceFill(['status' => UserStatus::Inactive])->save();
    app(ActiveCompanyContext::class)->set($company);

    expect($user->can(PermissionKey::CompanyView->value))->toBeFalse();
});

it('denies permissions through an inactive role', function (): void {
    $company = authorizationCompany('MARS');
    $user = authorizationUser('inactive-role@example.test');
    $membership = authorizationMembership($company, $user);
    $role = authorizationRole($company, 'viewer');

    app(GrantPermissionToRole::class)->handle($role, PermissionKey::CompanyView);
    app(AssignRoleToMembership::class)->handle($membership, $role);

    $role->forceFill(['is_active' => false])->save();
    app(ActiveCompanyContext::class)->set($company);

    expect($user->can(PermissionKey::CompanyView->value))->toBeFalse();
});

it('does not leak a role from one company into another active company context', function (): void {
    $companyA = authorizationCompany('COMP-A');
    $companyB = authorizationCompany('COMP-B');
    $user = authorizationUser('multi-company@example.test');
    $membershipA = authorizationMembership($companyA, $user);
    authorizationMembership($companyB, $user);
    $roleA = authorizationRole($companyA, 'manager');

    app(GrantPermissionToRole::class)->handle($roleA, PermissionKey::SettingsManage);
    app(AssignRoleToMembership::class)->handle($membershipA, $roleA);

    $context = app(ActiveCompanyContext::class);
    $context->set($companyA);
    expect($user->can(PermissionKey::SettingsManage->value))->toBeTrue();

    $context->set($companyB);
    expect($user->can(PermissionKey::SettingsManage->value))->toBeFalse();
});

it('rejects cross-company role assignment in both domain service and PostgreSQL constraints', function (): void {
    $companyA = authorizationCompany('COMP-A');
    $companyB = authorizationCompany('COMP-B');
    $user = authorizationUser('cross-company@example.test');
    $membershipB = authorizationMembership($companyB, $user);
    $roleA = authorizationRole($companyA, 'manager');

    expect(
        fn (): mixed => app(AssignRoleToMembership::class)->handle($membershipB, $roleA),
    )->toThrow(DomainException::class, 'Rol ve şirket üyeliği aynı şirkete ait olmalıdır.');

    expect(fn (): bool => DB::table('company_membership_roles')->insert([
        'company_id' => $companyB->getKey(),
        'membership_id' => $membershipB->getKey(),
        'role_id' => $roleA->getKey(),
    ]))->toThrow(QueryException::class);
});

it('revokes role permissions and membership assignments without residual access', function (): void {
    $company = authorizationCompany('MARS');
    $user = authorizationUser('revoke@example.test');
    $membership = authorizationMembership($company, $user);
    $role = authorizationRole($company, 'operator');
    $grant = app(GrantPermissionToRole::class);
    $assignment = app(AssignRoleToMembership::class);

    $grant->handle($role, PermissionKey::SettingsView);
    $assignment->handle($membership, $role);
    app(ActiveCompanyContext::class)->set($company);

    expect($user->can(PermissionKey::SettingsView->value))->toBeTrue();

    $grant->revoke($role, PermissionKey::SettingsView);
    expect($user->can(PermissionKey::SettingsView->value))->toBeFalse();

    $grant->handle($role, PermissionKey::SettingsView);
    $assignment->revoke($membership, $role);
    expect($user->can(PermissionKey::SettingsView->value))->toBeFalse();
});

function authorizationCompany(string $code): Company
{
    return Company::query()->create([
        'code' => $code,
        'name' => 'Authorization '.$code,
    ]);
}

function authorizationUser(string $email): User
{
    return User::query()->create([
        'name' => 'Authorization User',
        'email' => $email,
        'password' => 'correct-password',
        'status' => UserStatus::Active,
    ]);
}

function authorizationMembership(Company $company, User $user): CompanyMembership
{
    $companyId = $company->getKey();
    $userId = $user->getKey();

    if (! is_int($companyId) || ! is_int($userId)) {
        throw new LogicException('Authorization fixture requires persisted company and user records.');
    }

    return CompanyMembership::query()->create([
        'company_id' => $companyId,
        'user_id' => $userId,
        'is_active' => true,
        'joined_at' => now(),
    ]);
}

function authorizationRole(Company $company, string $code): Role
{
    $companyId = $company->getKey();

    if (! is_int($companyId)) {
        throw new LogicException('Authorization fixture requires a persisted company record.');
    }

    return Role::query()->create([
        'company_id' => $companyId,
        'code' => $code,
        'name' => ucfirst($code),
        'is_active' => true,
    ]);
}
