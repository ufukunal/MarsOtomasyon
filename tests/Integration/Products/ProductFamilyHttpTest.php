<?php

use App\Modules\Core\Authorization\AssignRoleToMembership;
use App\Modules\Core\Authorization\GrantPermissionToRole;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Products\Models\ProductFamily;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

it('keeps product family HTTP surface hidden while the feature is disabled', function (): void {
    [$company, $user] = m25HttpActor(PermissionKey::ProductView, 'm25-off@example.test');
    config()->set('mars.features.product_family_variant', false);

    $this->actingAs($user)->withSession(['active_company_id' => $company->getKey()])
        ->get(route('inventory.product-families.index'))
        ->assertNotFound();
});

it('enforces product permissions and company scope on family HTTP routes', function (): void {
    [$company, $viewer] = m25HttpActor(PermissionKey::ProductView, 'm25-view@example.test');
    [$foreignCompany] = m25HttpActor(PermissionKey::ProductView, 'm25-foreign@example.test');
    $family = ProductFamily::query()->create(['company_id' => $company->getKey(), 'code' => 'HTTP-A', 'name' => 'HTTP A']);
    $foreignFamily = ProductFamily::query()->create(['company_id' => $foreignCompany->getKey(), 'code' => 'HTTP-F', 'name' => 'HTTP F']);
    config()->set('mars.features.product_family_variant', true);

    $this->actingAs($viewer)->withSession(['active_company_id' => $company->getKey()])
        ->get(route('inventory.product-families.index'))->assertOk()->assertSee('Ürün Aileleri');
    $this->actingAs($viewer)->withSession(['active_company_id' => $company->getKey()])
        ->get(route('inventory.product-families.show', $foreignFamily))->assertNotFound();
    $this->actingAs($viewer)->withSession(['active_company_id' => $company->getKey()])
        ->get(route('inventory.product-families.edit', $family))->assertForbidden();
});

it('serves server-side catalog rows for family simple and variant filters', function (): void {
    [$company, $user] = m25HttpActor(PermissionKey::ProductView, 'm25-data@example.test');
    ProductFamily::query()->create(['company_id' => $company->getKey(), 'code' => 'HTTP-DATA', 'name' => 'HTTP Data']);
    m25SchemaProduct($company, 'SKU-HTTP-SIMPLE');
    config()->set('mars.features.product_family_variant', true);

    $this->actingAs($user)->withSession(['active_company_id' => $company->getKey()])
        ->getJson(route('inventory.product-families.data', ['draw' => 7, 'type' => 'all', 'length' => 25]))
        ->assertOk()
        ->assertJsonPath('draw', 7)
        ->assertJsonPath('recordsTotal', 2)
        ->assertJsonCount(2, 'data');
});

/** @return array{Company,User} */
function m25HttpActor(PermissionKey $permission, string $email): array
{
    $company = Company::query()->create(['code' => strtoupper(substr(hash('sha256', $email), 0, 10)), 'name' => 'M25 HTTP Company']);
    $user = User::query()->create(['name' => 'M25 HTTP User', 'email' => $email, 'password' => 'not-used', 'status' => UserStatus::Active]);
    $membership = CompanyMembership::query()->create(['company_id' => $company->getKey(), 'user_id' => $user->getKey(), 'is_active' => true, 'joined_at' => now()]);
    $role = Role::query()->create(['company_id' => $company->getKey(), 'code' => 'm25-'.substr(hash('sha1', $email), 0, 8), 'name' => 'M25 Role', 'is_active' => true]);
    app(GrantPermissionToRole::class)->handle($role, $permission);
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return [$company, $user];
}
