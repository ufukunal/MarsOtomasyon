<?php

use App\Modules\Core\Authorization\AssignRoleToMembership;
use App\Modules\Core\Authorization\GrantPermissionToRole;
use App\Modules\Core\Branch\ActiveBranchContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\AuditEntry;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Route;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
    registerM1BranchContextRoute();
});

it('creates and updates company branches with immutable audit events', function (): void {
    $company = m1BranchCompany('BR-A');
    $actor = m1BranchActor($company, [PermissionKey::BranchView, PermissionKey::BranchManage], 'manager');

    $this->actingAs($actor)
        ->withHeader('X-Correlation-ID', 'branch-create-001')
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/settings/branches', [
            'code' => 'ist',
            'name' => 'İstanbul',
            'is_active' => '1',
        ])
        ->assertRedirect();

    $branch = Branch::query()->firstOrFail();
    expect($branch->code)->toBe('IST')
        ->and($branch->company_id)->toBe($company->getKey())
        ->and($branch->is_active)->toBeTrue();

    $created = AuditEntry::query()->where('action', AuditAction::BranchCreated->value)->firstOrFail();
    expect($created->correlation_id)->toBe('branch-create-001')
        ->and($created->after_state['code'])->toBe('IST')
        ->and($created->after_state['is_active'])->toBeTrue();

    $this->actingAs($actor)
        ->withHeader('X-Correlation-ID', 'branch-update-001')
        ->withSession(['active_company_id' => $company->getKey()])
        ->put('/settings/branches/'.$branch->getKey(), [
            'code' => 'IST',
            'name' => 'İstanbul Merkez',
            'is_active' => '1',
        ])
        ->assertRedirect();

    $updated = AuditEntry::query()->where('action', AuditAction::BranchUpdated->value)->firstOrFail();
    expect($updated->correlation_id)->toBe('branch-update-001')
        ->and($updated->before_state['name'])->toBe('İstanbul')
        ->and($updated->after_state['name'])->toBe('İstanbul Merkez');
});

it('enforces separate branch view and manage permissions', function (): void {
    $company = m1BranchCompany('BR-B');
    $viewer = m1BranchActor($company, [PermissionKey::BranchView], 'viewer');

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/settings/branches')
        ->assertOk();

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/settings/branches', [
            'code' => 'ANK',
            'name' => 'Ankara',
            'is_active' => '1',
        ])
        ->assertForbidden();
});

it('keeps branch records and selection company scoped', function (): void {
    $companyA = m1BranchCompany('BR-C-A');
    $companyB = m1BranchCompany('BR-C-B');
    $actorA = m1BranchActor($companyA, [PermissionKey::BranchView], 'viewer-a');
    $foreign = m1Branch($companyB, 'FOREIGN', true);

    $this->actingAs($actorA)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/settings/branches/'.$foreign->getKey())
        ->assertNotFound();

    $this->actingAs($actorA)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->post('/settings/branches/'.$foreign->getKey().'/select')
        ->assertNotFound();
});

it('auto selects the only active branch when branch context is required', function (): void {
    $company = m1BranchCompany('BR-D');
    $actor = m1BranchActor($company, [PermissionKey::BranchView], 'viewer');
    $branch = m1Branch($company, 'ONLY', true);

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/_test/branch-context')
        ->assertOk()
        ->assertJson(['branch_id' => $branch->getKey()])
        ->assertSessionHas('active_branch_id', $branch->getKey());
});

it('requires explicit branch selection when multiple active branches exist', function (): void {
    $company = m1BranchCompany('BR-E');
    $actor = m1BranchActor($company, [PermissionKey::BranchView], 'viewer');
    m1Branch($company, 'ONE', true);
    m1Branch($company, 'TWO', true);

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/_test/branch-context')
        ->assertStatus(409);
});

it('uses an explicitly selected active branch and rejects inactive or foreign session branches', function (): void {
    $company = m1BranchCompany('BR-F');
    $foreignCompany = m1BranchCompany('BR-F-X');
    $actor = m1BranchActor($company, [PermissionKey::BranchView], 'viewer');
    $first = m1Branch($company, 'ONE', true);
    $selected = m1Branch($company, 'TWO', true);
    $inactive = m1Branch($company, 'OFF', false);
    $foreign = m1Branch($foreignCompany, 'FOREIGN', true);

    expect($first->getKey())->not->toBe($selected->getKey());

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/settings/branches/'.$selected->getKey().'/select')
        ->assertRedirect()
        ->assertSessionHas('active_branch_id', $selected->getKey());

    $this->actingAs($actor)
        ->withSession([
            'active_company_id' => $company->getKey(),
            'active_branch_id' => $selected->getKey(),
        ])
        ->get('/_test/branch-context')
        ->assertOk()
        ->assertJson(['branch_id' => $selected->getKey()]);

    $this->actingAs($actor)
        ->withSession([
            'active_company_id' => $company->getKey(),
            'active_branch_id' => $inactive->getKey(),
        ])
        ->get('/_test/branch-context')
        ->assertForbidden();

    $this->actingAs($actor)
        ->withSession([
            'active_company_id' => $company->getKey(),
            'active_branch_id' => $foreign->getKey(),
        ])
        ->get('/_test/branch-context')
        ->assertForbidden();
});

it('clears the current session branch when that branch is deactivated', function (): void {
    $company = m1BranchCompany('BR-G');
    $actor = m1BranchActor($company, [PermissionKey::BranchView, PermissionKey::BranchManage], 'manager');
    $branch = m1Branch($company, 'ACTIVE', true);

    $this->actingAs($actor)
        ->withSession([
            'active_company_id' => $company->getKey(),
            'active_branch_id' => $branch->getKey(),
        ])
        ->put('/settings/branches/'.$branch->getKey(), [
            'code' => 'ACTIVE',
            'name' => 'Artık Pasif',
            'is_active' => '0',
        ])
        ->assertRedirect()
        ->assertSessionMissing('active_branch_id');

    expect($branch->refresh()->is_active)->toBeFalse();
});

it('rejects case insensitive duplicate branch codes within one company', function (): void {
    $company = m1BranchCompany('BR-H');
    $actor = m1BranchActor($company, [PermissionKey::BranchView, PermissionKey::BranchManage], 'manager');
    m1Branch($company, 'IST', true);

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->from('/settings/branches/create')
        ->post('/settings/branches', [
            'code' => 'ist',
            'name' => 'Duplicate',
            'is_active' => '1',
        ])
        ->assertRedirect('/settings/branches/create')
        ->assertSessionHasErrors('code');

    expect(Branch::query()->where('company_id', $company->getKey())->count())->toBe(1);
});

it('rejects branch context when the active company has no active branch', function (): void {
    $company = m1BranchCompany('BR-I');
    $actor = m1BranchActor($company, [PermissionKey::BranchView], 'viewer');
    m1Branch($company, 'OFF', false);

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/_test/branch-context')
        ->assertStatus(409);
});

function registerM1BranchContextRoute(): void
{
    if (Route::has('test.branch.context')) {
        return;
    }

    Route::get('/_test/branch-context', function (ActiveBranchContext $context): array {
        return ['branch_id' => $context->requireBranch()->getKey()];
    })
        ->middleware(['web', 'auth', 'company.context', 'branch.context'])
        ->name('test.branch.context');
}

function m1BranchCompany(string $code): Company
{
    return Company::query()->create([
        'code' => $code,
        'name' => 'Company '.$code,
    ]);
}

function m1Branch(Company $company, string $code, bool $active): Branch
{
    return Branch::query()->create([
        'company_id' => $company->getKey(),
        'code' => $code,
        'name' => 'Branch '.$code,
        'is_active' => $active,
    ]);
}

/** @param list<PermissionKey> $permissions */
function m1BranchActor(Company $company, array $permissions, string $suffix): User
{
    $user = User::query()->create([
        'name' => 'Branch '.$suffix,
        'email' => strtolower((string) $company->code).'-'.$suffix.'@branches.test',
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
        'code' => 'branches-'.$suffix,
        'name' => 'Branches '.$suffix,
        'is_active' => true,
    ]);

    foreach ($permissions as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }

    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}
