<?php

use App\Modules\Core\Authorization\AssignRoleToMembership;
use App\Modules\Core\Authorization\GrantPermissionToRole;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\Tax;
use App\Modules\Core\Models\User;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('keeps product viewing separate from product management', function (): void {
    [$company, $category, $unit, $tax] = m32AuthCatalog('M32-AUTH-A');
    $viewer = m32AuthActor($company, [PermissionKey::ProductView], 'viewer');
    $product = m32AuthProduct($company, $category, $unit, $tax, 'VIEW-SKU');

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/inventory')
        ->assertOk()
        ->assertSee('VIEW-SKU')
        ->assertDontSee('Yeni Ürün');

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/inventory/products/'.$product->getKey())
        ->assertOk()
        ->assertDontSee('Düzenle');

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/inventory/products/create')
        ->assertForbidden();

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/inventory/products/'.$product->getKey().'/edit')
        ->assertForbidden();
});

it('denies product screens without product permissions', function (): void {
    [$company] = m32AuthCatalog('M32-AUTH-B');
    $actor = m32AuthActor($company, [], 'none');

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/inventory')
        ->assertForbidden();
});

it('does not expose or mutate another company product by route id', function (): void {
    [$companyA, $categoryA, $unitA, $taxA] = m32AuthCatalog('M32-AUTH-C-A');
    [$companyB, $categoryB, $unitB, $taxB] = m32AuthCatalog('M32-AUTH-C-B');
    $manager = m32AuthActor($companyA, [PermissionKey::ProductView, PermissionKey::ProductManage], 'manager');
    $foreign = m32AuthProduct($companyB, $categoryB, $unitB, $taxB, 'FOREIGN-SKU');

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/inventory/products/'.$foreign->getKey())
        ->assertNotFound();

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/inventory/products/'.$foreign->getKey().'/edit')
        ->assertNotFound();

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->put('/inventory/products/'.$foreign->getKey(), [
            'code' => 'STOLEN-SKU',
            'status' => ProductStatus::Inactive->value,
            'name' => 'Cross Company Mutation',
            'category_id' => $categoryA->getKey(),
            'unit_id' => $unitA->getKey(),
            'tax_id' => $taxA->getKey(),
            'sale_price_net' => '1.000000',
            'purchase_price_net' => '1.000000',
            'primary_barcode' => null,
            'additional_barcodes' => null,
        ])
        ->assertNotFound();

    expect($foreign->refresh()->code)->toBe('FOREIGN-SKU')
        ->and($foreign->statusEnum())->toBe(ProductStatus::Active);
});

/** @return array{Company, Category, Unit, Tax} */
function m32AuthCatalog(string $code): array
{
    $company = Company::query()->create([
        'code' => $code,
        'name' => 'Company '.$code,
    ]);
    $category = Category::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'CAT',
        'name' => 'Kategori',
        'is_active' => true,
    ]);
    $unit = Unit::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'ADET',
        'name' => 'Adet',
        'is_active' => true,
    ]);
    $tax = Tax::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'KDV20',
        'name' => 'KDV %20',
        'rate' => '20.000000',
        'is_active' => true,
    ]);

    return [$company, $category, $unit, $tax];
}

/** @param list<PermissionKey> $permissions */
function m32AuthActor(Company $company, array $permissions, string $suffix): User
{
    $user = User::query()->create([
        'name' => 'M3.2 '.$suffix,
        'email' => strtolower((string) $company->code).'-'.$suffix.'@m32-auth.test',
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
        'code' => 'm32-'.$suffix,
        'name' => 'M3.2 '.$suffix,
        'is_active' => true,
    ]);

    foreach ($permissions as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}

function m32AuthProduct(Company $company, Category $category, Unit $unit, Tax $tax, string $code): Product
{
    return Product::query()->create([
        'company_id' => $company->getKey(),
        'code' => $code,
        'status' => ProductStatus::Active,
        'name' => 'Ürün '.$code,
        'category_id' => $category->getKey(),
        'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(),
        'sale_price_net' => '100.000000',
        'purchase_price_net' => '60.000000',
    ]);
}
