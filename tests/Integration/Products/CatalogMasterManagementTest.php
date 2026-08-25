<?php

use App\Foundation\Correlation\CorrelationContext;
use App\Modules\Core\Authorization\AssignRoleToMembership;
use App\Modules\Core\Authorization\GrantPermissionToRole;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\AuditEntry;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\Tax;
use App\Modules\Core\Models\User;
use App\Modules\Products\Actions\CatalogMasterData;
use App\Modules\Products\Actions\ManageCategory;
use App\Modules\Products\Actions\ManageUnit;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Validation\ValidationException;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('creates and updates category and unit with normalization lifecycle and audit evidence', function (): void {
    [$company, $actor] = m33DomainContext('M33-DOMAIN-A');

    $category = app(ManageCategory::class)->create(new CatalogMasterData(
        code: ' avize-salon ',
        name: ' Salon Avizeleri ',
        isActive: true,
    ));
    $unit = app(ManageUnit::class)->create(new CatalogMasterData(
        code: ' adet ',
        name: ' Adet ',
        isActive: true,
    ));

    expect($category->company_id)->toBe($company->getKey())
        ->and($category->code)->toBe('AVIZE-SALON')
        ->and($category->name)->toBe('Salon Avizeleri')
        ->and($category->is_active)->toBeTrue()
        ->and($unit->company_id)->toBe($company->getKey())
        ->and($unit->code)->toBe('ADET')
        ->and($unit->name)->toBe('Adet')
        ->and($unit->is_active)->toBeTrue();

    app(CorrelationContext::class)->set('m3-3-update');
    test()->actingAs($actor);

    $category = app(ManageCategory::class)->update($category->getKey(), new CatalogMasterData(
        code: ' avize-modern ',
        name: ' Modern Avizeler ',
        isActive: false,
    ));
    $unit = app(ManageUnit::class)->update($unit->getKey(), new CatalogMasterData(
        code: ' pcs ',
        name: ' Parça ',
        isActive: false,
    ));

    expect($category->code)->toBe('AVIZE-MODERN')
        ->and($category->name)->toBe('Modern Avizeler')
        ->and($category->is_active)->toBeFalse()
        ->and($unit->code)->toBe('PCS')
        ->and($unit->name)->toBe('Parça')
        ->and($unit->is_active)->toBeFalse();

    $categoryAudit = AuditEntry::query()
        ->where('action', AuditAction::CategoryUpdated->value)
        ->where('target_id', $category->getKey())
        ->firstOrFail();
    $unitAudit = AuditEntry::query()
        ->where('action', AuditAction::UnitUpdated->value)
        ->where('target_id', $unit->getKey())
        ->firstOrFail();

    expect($categoryAudit->before_state['code'])->toBe('AVIZE-SALON')
        ->and($categoryAudit->after_state['code'])->toBe('AVIZE-MODERN')
        ->and($categoryAudit->before_state['is_active'])->toBeTrue()
        ->and($categoryAudit->after_state['is_active'])->toBeFalse()
        ->and($unitAudit->before_state['code'])->toBe('ADET')
        ->and($unitAudit->after_state['code'])->toBe('PCS')
        ->and($unitAudit->before_state['is_active'])->toBeTrue()
        ->and($unitAudit->after_state['is_active'])->toBeFalse();
});

it('blocks case insensitive duplicate category and unit codes in application and PostgreSQL', function (): void {
    [$company] = m33DomainContext('M33-DUP-A');

    app(ManageCategory::class)->create(new CatalogMasterData('LIGHTING', 'Aydınlatma', true));
    app(ManageUnit::class)->create(new CatalogMasterData('ADET', 'Adet', true));

    expect(fn () => app(ManageCategory::class)->create(new CatalogMasterData('lighting', 'Tekrar', true)))
        ->toThrow(ValidationException::class);
    expect(fn () => app(ManageUnit::class)->create(new CatalogMasterData('adet', 'Tekrar', true)))
        ->toThrow(ValidationException::class);

    expect(fn () => Category::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'lighting',
        'name' => 'Race Category',
        'is_active' => true,
    ]))->toThrow(QueryException::class);
    expect(fn () => Unit::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'adet',
        'name' => 'Race Unit',
        'is_active' => true,
    ]))->toThrow(QueryException::class);

    expect(Category::query()->where('company_id', $company->getKey())->count())->toBe(1)
        ->and(Unit::query()->where('company_id', $company->getKey())->count())->toBe(1);
});

it('cannot update another company category or unit through active company context', function (): void {
    [$companyA] = m33DomainContext('M33-TENANT-A');
    $categoryA = Category::query()->create([
        'company_id' => $companyA->getKey(),
        'code' => 'A-CAT',
        'name' => 'A Category',
        'is_active' => true,
    ]);
    $unitA = Unit::query()->create([
        'company_id' => $companyA->getKey(),
        'code' => 'A-UNIT',
        'name' => 'A Unit',
        'is_active' => true,
    ]);

    m33DomainContext('M33-TENANT-B');

    expect(fn () => app(ManageCategory::class)->update($categoryA->getKey(), new CatalogMasterData('STOLEN', 'Cross', false)))
        ->toThrow(ModelNotFoundException::class);
    expect(fn () => app(ManageUnit::class)->update($unitA->getKey(), new CatalogMasterData('STOLEN', 'Cross', false)))
        ->toThrow(ModelNotFoundException::class);

    expect($categoryA->refresh()->code)->toBe('A-CAT')
        ->and($categoryA->is_active)->toBeTrue()
        ->and($unitA->refresh()->code)->toBe('A-UNIT')
        ->and($unitA->is_active)->toBeTrue();
});

it('keeps catalog viewing separate from catalog management and protects foreign route ids', function (): void {
    $companyA = m33Company('M33-AUTH-A');
    $companyB = m33Company('M33-AUTH-B');
    $viewer = m33Actor($companyA, [PermissionKey::ProductView], 'viewer');
    $manager = m33Actor($companyA, [PermissionKey::ProductView, PermissionKey::ProductManage], 'manager');
    $none = m33Actor($companyA, [], 'none');

    $categoryA = m33Category($companyA, 'OWN-CAT', 'Own Category');
    $unitA = m33Unit($companyA, 'OWN-UNIT', 'Own Unit');
    $foreignCategory = m33Category($companyB, 'FOREIGN-CAT', 'Foreign Category');
    $foreignUnit = m33Unit($companyB, 'FOREIGN-UNIT', 'Foreign Unit');

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/inventory/categories')
        ->assertOk()
        ->assertSee($categoryA->name)
        ->assertDontSee('Yeni Kategori');
    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/inventory/units')
        ->assertOk()
        ->assertSee($unitA->name)
        ->assertDontSee('Yeni Birim');
    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/inventory/categories/create')
        ->assertForbidden();
    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/inventory/units/create')
        ->assertForbidden();

    $this->actingAs($none)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/inventory/categories')
        ->assertForbidden();
    $this->actingAs($none)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/inventory/units')
        ->assertForbidden();

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/inventory/categories/'.$foreignCategory->getKey().'/edit')
        ->assertNotFound();
    $this->actingAs($manager)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->put('/inventory/categories/'.$foreignCategory->getKey(), [
            'code' => 'STOLEN-CAT',
            'name' => 'Cross Company',
            'status' => 'inactive',
        ])
        ->assertNotFound();
    $this->actingAs($manager)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/inventory/units/'.$foreignUnit->getKey().'/edit')
        ->assertNotFound();
    $this->actingAs($manager)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->put('/inventory/units/'.$foreignUnit->getKey(), [
            'code' => 'STOLEN-UNIT',
            'name' => 'Cross Company',
            'status' => 'inactive',
        ])
        ->assertNotFound();

    expect($foreignCategory->refresh()->code)->toBe('FOREIGN-CAT')
        ->and($foreignCategory->is_active)->toBeTrue()
        ->and($foreignUnit->refresh()->code)->toBe('FOREIGN-UNIT')
        ->and($foreignUnit->is_active)->toBeTrue();
});

it('preserves inactive masters on existing products while excluding them from new product choices', function (): void {
    $company = m33Company('M33-LIFECYCLE');
    $manager = m33Actor($company, [PermissionKey::ProductView, PermissionKey::ProductManage], 'lifecycle');
    $category = m33Category($company, 'LEGACY-CAT', 'Legacy Category');
    $unit = m33Unit($company, 'LEGACY-UNIT', 'Legacy Unit');
    $tax = m33Tax($company);
    $product = Product::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'LEGACY-SKU',
        'status' => ProductStatus::Active,
        'name' => 'Legacy Product',
        'category_id' => $category->getKey(),
        'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(),
        'sale_price_net' => '100.000000',
        'purchase_price_net' => '50.000000',
    ]);

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->put('/inventory/categories/'.$category->getKey(), [
            'code' => 'LEGACY-CAT',
            'name' => 'Legacy Category',
            'status' => 'inactive',
        ])
        ->assertRedirect();
    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->put('/inventory/units/'.$unit->getKey(), [
            'code' => 'LEGACY-UNIT',
            'name' => 'Legacy Unit',
            'status' => 'inactive',
        ])
        ->assertRedirect();

    expect($product->refresh()->category_id)->toBe($category->getKey())
        ->and($product->unit_id)->toBe($unit->getKey())
        ->and($category->refresh()->is_active)->toBeFalse()
        ->and($unit->refresh()->is_active)->toBeFalse();

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/inventory/products/create')
        ->assertOk()
        ->assertDontSee('LEGACY-CAT · Legacy Category · Pasif')
        ->assertDontSee('LEGACY-UNIT · Legacy Unit · Pasif');

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/inventory/products/'.$product->getKey().'/edit')
        ->assertOk()
        ->assertSee('LEGACY-CAT · Legacy Category · Pasif')
        ->assertSee('LEGACY-UNIT · Legacy Unit · Pasif');
});

/** @return array{Company, User} */
function m33DomainContext(string $companyCode): array
{
    $company = m33Company($companyCode);
    $actor = User::query()->create([
        'name' => 'M3.3 Domain '.$companyCode,
        'email' => strtolower($companyCode).'@m33-domain.test',
        'password' => 'correct-password',
        'status' => UserStatus::Active,
    ]);

    app(ActiveCompanyContext::class)->set($company);
    app(CorrelationContext::class)->set('m3-3-catalog-master-test');
    test()->actingAs($actor);

    return [$company, $actor];
}

function m33Company(string $code): Company
{
    return Company::query()->create([
        'code' => $code,
        'name' => 'Company '.$code,
    ]);
}

/** @param list<PermissionKey> $permissions */
function m33Actor(Company $company, array $permissions, string $suffix): User
{
    $user = User::query()->create([
        'name' => 'M3.3 '.$suffix,
        'email' => strtolower((string) $company->code).'-'.$suffix.'@m33-auth.test',
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
        'code' => 'm33-'.$suffix,
        'name' => 'M3.3 '.$suffix,
        'is_active' => true,
    ]);

    foreach ($permissions as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}

function m33Category(Company $company, string $code, string $name): Category
{
    return Category::query()->create([
        'company_id' => $company->getKey(),
        'code' => $code,
        'name' => $name,
        'is_active' => true,
    ]);
}

function m33Unit(Company $company, string $code, string $name): Unit
{
    return Unit::query()->create([
        'company_id' => $company->getKey(),
        'code' => $code,
        'name' => $name,
        'is_active' => true,
    ]);
}

function m33Tax(Company $company): Tax
{
    return Tax::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'KDV20',
        'name' => 'KDV %20',
        'rate' => '20.000000',
        'is_active' => true,
    ]);
}
