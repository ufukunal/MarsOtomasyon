<?php

use App\Modules\Core\Authorization\AssignRoleToMembership;
use App\Modules\Core\Authorization\GrantPermissionToRole;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('requires company-scoped permissions for user management routes', function (): void {
    $company = managementCompany('M1-4-A');
    $allowed = managementActor($company, [PermissionKey::UserView]);
    $denied = managementActor($company, []);

    $this->actingAs($allowed)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/settings/users')
        ->assertOk();

    $this->actingAs($denied)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/settings/users')
        ->assertForbidden();
});

it('creates a company user and assigns only grantable roles', function (): void {
    $company = managementCompany('M1-4-B');
    $actor = managementActor($company, [PermissionKey::UserView, PermissionKey::UserManage]);
    $targetRole = managementRole($company, 'viewer', [PermissionKey::UserView]);

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/settings/users', [
            'name' => 'Yeni Kullanıcı',
            'email' => 'NEW.USER@example.test',
            'password' => 'very-secure-password',
            'role_ids' => [$targetRole->getKey()],
        ])
        ->assertRedirect();

    $user = User::query()->where('email', 'new.user@example.test')->firstOrFail();
    $membership = CompanyMembership::query()
        ->where('company_id', $company->getKey())
        ->where('user_id', $user->getKey())
        ->firstOrFail();

    expect($membership->roles()->whereKey($targetRole->getKey())->exists())->toBeTrue();
});

it('blocks privilege escalation through user role assignment', function (): void {
    $company = managementCompany('M1-4-C');
    $actor = managementActor($company, [PermissionKey::UserView, PermissionKey::UserManage]);
    $elevatedRole = managementRole($company, 'settings-admin', [PermissionKey::SettingsManage]);

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/settings/users', [
            'name' => 'Escalation Attempt',
            'email' => 'escalation@example.test',
            'password' => 'very-secure-password',
            'role_ids' => [$elevatedRole->getKey()],
        ])
        ->assertForbidden();

    expect(User::query()->where('email', 'escalation@example.test')->exists())->toBeFalse();
});

it('does not expose another company membership by route id', function (): void {
    $companyA = managementCompany('M1-4-D-A');
    $companyB = managementCompany('M1-4-D-B');
    $actor = managementActor($companyA, [PermissionKey::UserView]);
    $targetUser = managementUser('other-company@example.test');
    $foreignMembership = managementMembership($companyB, $targetUser);

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/settings/users/'.$foreignMembership->getKey())
        ->assertNotFound();
});

it('keeps shared user identity readonly while allowing company membership updates', function (): void {
    $companyA = managementCompany('M1-4-E-A');
    $companyB = managementCompany('M1-4-E-B');
    $actor = managementActor($companyA, [PermissionKey::UserView, PermissionKey::UserManage]);
    $sharedUser = managementUser('shared@example.test');
    $membershipA = managementMembership($companyA, $sharedUser);
    managementMembership($companyB, $sharedUser);

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->put('/settings/users/'.$membershipA->getKey(), [
            'name' => 'Hacked Name',
            'email' => 'hacked@example.test',
            'password' => 'changed-password',
            'is_active' => '0',
            'role_ids' => [],
        ])
        ->assertRedirect();

    expect($sharedUser->fresh()?->name)->not->toBe('Hacked Name')
        ->and($sharedUser->fresh()?->email)->toBe('shared@example.test')
        ->and($membershipA->fresh()?->is_active)->toBeFalse();
});

it('creates roles only with permissions the actor already owns', function (): void {
    $company = managementCompany('M1-4-F');
    $actor = managementActor($company, [
        PermissionKey::RoleView,
        PermissionKey::RoleManage,
        PermissionKey::UserView,
    ]);

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/settings/roles', [
            'code' => 'support',
            'name' => 'Destek',
            'is_active' => '1',
            'permission_keys' => [PermissionKey::UserView->value],
        ])
        ->assertRedirect();

    $role = Role::query()->where('company_id', $company->getKey())->where('code', 'support')->firstOrFail();
    expect($role->permissions()->where('key', PermissionKey::UserView->value)->exists())->toBeTrue();

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/settings/roles', [
            'code' => 'forbidden-admin',
            'name' => 'Yasak Yetki',
            'is_active' => '1',
            'permission_keys' => [PermissionKey::SettingsManage->value],
        ])
        ->assertForbidden();

    expect(Role::query()->where('company_id', $company->getKey())->where('code', 'forbidden-admin')->exists())->toBeFalse();
});

it('keeps role detail readonly and edit on a separate route', function (): void {
    $company = managementCompany('M1-4-G');
    $actor = managementActor($company, [PermissionKey::RoleView, PermissionKey::RoleManage]);
    $role = managementRole($company, 'readonly-role', [PermissionKey::RoleView]);

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/settings/roles/'.$role->getKey())
        ->assertOk()
        ->assertSee('Rol Detayı')
        ->assertDontSee('name="code"', false);

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/settings/roles/'.$role->getKey().'/edit')
        ->assertOk()
        ->assertSee('name="code"', false);
});

function managementCompany(string $code): Company
{
    return Company::query()->create(['code' => $code, 'name' => 'Management '.$code]);
}

function managementUser(string $email): User
{
    return User::query()->create([
        'name' => 'Management User',
        'email' => $email,
        'password' => 'correct-password',
        'status' => UserStatus::Active,
    ]);
}

function managementMembership(Company $company, User $user): CompanyMembership
{
    return CompanyMembership::query()->create([
        'company_id' => $company->getKey(),
        'user_id' => $user->getKey(),
        'is_active' => true,
        'joined_at' => now(),
    ]);
}

/** @param list<PermissionKey> $permissions */
function managementActor(Company $company, array $permissions): User
{
    static $sequence = 0;

    $sequence++;
    $companyCode = strtolower((string) $company->code);
    $user = managementUser($companyCode.'-'.$sequence.'@manager.test');
    $membership = managementMembership($company, $user);
    $role = managementRole($company, 'manager-'.$companyCode.'-'.$sequence, $permissions);
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}

/** @param list<PermissionKey> $permissions */
function managementRole(Company $company, string $code, array $permissions): Role
{
    $role = Role::query()->create([
        'company_id' => $company->getKey(),
        'code' => $code,
        'name' => ucfirst($code),
        'is_active' => true,
    ]);

    foreach ($permissions as $permission) {
        $record = Permission::query()->where('key', $permission->value)->firstOrFail();
        app(GrantPermissionToRole::class)->handle($role, $permission);
        expect($record->key)->toBe($permission->value);
    }

    return $role;
}
