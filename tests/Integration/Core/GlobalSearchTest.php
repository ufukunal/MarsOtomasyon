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

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('searches authorized core records with PostgreSQL FTS and trigram ranking inside the active company', function (): void {
    $company = globalSearchCompany('SEARCH-A');
    $foreignCompany = globalSearchCompany('SEARCH-B');
    $actor = globalSearchActor($company, [
        PermissionKey::BranchView,
        PermissionKey::UserView,
        PermissionKey::RoleView,
    ]);

    Branch::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'ANK-MRK',
        'name' => 'Ankara Merkez',
        'is_active' => true,
    ]);
    Branch::query()->create([
        'company_id' => $foreignCompany->getKey(),
        'code' => 'ANK-DIS',
        'name' => 'Ankara Dış Şube',
        'is_active' => true,
    ]);

    $targetUser = User::query()->create([
        'name' => 'Selin Operasyon',
        'email' => 'selin.operasyon@search.test',
        'password' => 'correct-password',
        'status' => UserStatus::Active,
    ]);
    CompanyMembership::query()->create([
        'company_id' => $company->getKey(),
        'user_id' => $targetUser->getKey(),
        'is_active' => true,
        'joined_at' => now(),
    ]);

    Role::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'DEPO-SORUMLUSU',
        'name' => 'Depo Sorumlusu',
        'is_active' => true,
    ]);

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/search?q=Ankara')
        ->assertOk()
        ->assertSee('Ankara Merkez')
        ->assertDontSee('Ankara Dış Şube');

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/search?q=Ankra')
        ->assertOk()
        ->assertSee('Ankara Merkez');

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/search?q=Selin')
        ->assertOk()
        ->assertSee('Selin Operasyon');

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/search?q=Depo')
        ->assertOk()
        ->assertSee('Depo Sorumlusu');
});

it('does not leak record types the actor cannot view', function (): void {
    $company = globalSearchCompany('SEARCH-C');
    $actor = globalSearchActor($company, [PermissionKey::UserView]);

    Branch::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'GIZLI',
        'name' => 'Gizli Şube',
        'is_active' => true,
    ]);

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/search?q=Gizli')
        ->assertOk()
        ->assertDontSee('Gizli Şube');
});

it('requires at least two characters before executing a global search', function (): void {
    $company = globalSearchCompany('SEARCH-D');
    $actor = globalSearchActor($company, [PermissionKey::BranchView]);

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/search?q=A')
        ->assertOk()
        ->assertSee('Arama için en az 2 karakter girin.');
});

function globalSearchCompany(string $code): Company
{
    return Company::query()->create([
        'code' => $code,
        'name' => 'Company '.$code,
    ]);
}

/** @param list<PermissionKey> $permissions */
function globalSearchActor(Company $company, array $permissions): User
{
    $user = User::query()->create([
        'name' => 'Search Manager '.$company->code,
        'email' => mb_strtolower((string) $company->code).'@search.test',
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
        'code' => 'SEARCH-MANAGER',
        'name' => 'Search Manager',
        'is_active' => true,
    ]);

    foreach ($permissions as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }

    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}
