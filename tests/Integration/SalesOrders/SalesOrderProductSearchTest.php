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
use App\Modules\Products\Models\Barcode;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

it('reuses ranked company product search for order entry without requiring product view permission', function (): void {
    [$company, $category, $unit, $tax] = m62Catalog('M62-A');
    $manager = m62Actor($company, [PermissionKey::SalesOrderManage], 'manager');

    $barcodeMatch = m62Product($company, $category, $unit, $tax, 'BAR-ITEM', 'Tarayıcı Ürünü');
    Barcode::query()->create([
        'company_id' => $company->getKey(),
        'product_id' => $barcodeMatch->getKey(),
        'barcode' => 'QR:MODEL-42',
        'is_primary' => true,
    ]);
    $skuMatch = m62Product($company, $category, $unit, $tax, 'MODEL-42', 'Model Kırk İki');
    m62Product($company, $category, $unit, $tax, 'OTHER', 'Model 42 Benzer Ad');

    $barcodeResponse = $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->getJson('/sales-orders/product-search?q='.urlencode('QR:MODEL-42'));

    $barcodeResponse->assertOk()
        ->assertJsonPath('data.0.id', $barcodeMatch->getKey())
        ->assertJsonPath('data.0.code', 'BAR-ITEM')
        ->assertJsonPath('data.0.sale_price_net', '100.000000')
        ->assertJsonPath('data.0.tax_code', 'KDV20')
        ->assertJsonPath('data.0.tax_rate', '20.000000');

    $skuResponse = $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->getJson('/sales-orders/product-search?q=MODEL-42');

    $skuResponse->assertOk()
        ->assertJsonPath('data.0.id', $skuMatch->getKey())
        ->assertJsonPath('data.0.label', 'MODEL-42 — Model Kırk İki');

    expect($manager->can(PermissionKey::ProductView->value))->toBeFalse();
});

it('keeps order product search active-company scoped and behind sales order management permission', function (): void {
    [$companyA, $categoryA, $unitA, $taxA] = m62Catalog('M62-SCOPE-A');
    [$companyB, $categoryB, $unitB, $taxB] = m62Catalog('M62-SCOPE-B');

    $activeA = m62Product($companyA, $categoryA, $unitA, $taxA, 'LAMP-ACTIVE', 'Ortak Arama Lambası');
    m62Product($companyA, $categoryA, $unitA, $taxA, 'LAMP-INACTIVE', 'Ortak Arama Lambası Pasif', ProductStatus::Inactive);
    m62Product($companyB, $categoryB, $unitB, $taxB, 'LAMP-FOREIGN', 'Ortak Arama Lambası Yabancı');

    $inactiveTax = Tax::query()->create([
        'company_id' => $companyA->getKey(),
        'code' => 'KDV18-OFF',
        'name' => 'KDV %18 Pasif',
        'rate' => '18.000000',
        'is_active' => false,
    ]);
    m62Product($companyA, $categoryA, $unitA, $inactiveTax, 'LAMP-INACTIVE-TAX', 'Ortak Arama Lambası Pasif Vergi');

    $manager = m62Actor($companyA, [PermissionKey::SalesOrderManage], 'scope-manager');
    $viewer = m62Actor($companyA, [PermissionKey::SalesOrderView], 'viewer');
    $productViewer = m62Actor($companyA, [PermissionKey::ProductView], 'product-viewer');

    $response = $this->actingAs($manager)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->getJson('/sales-orders/product-search?q=Ortak');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toBe([$activeA->getKey()]);

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->getJson('/sales-orders/product-search?q=LAMP')
        ->assertForbidden();

    $this->actingAs($productViewer)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->getJson('/sales-orders/product-search?q=LAMP')
        ->assertForbidden();
});

/** @return array{Company, Category, Unit, Tax} */
function m62Catalog(string $code): array
{
    $company = Company::query()->create(['code' => $code, 'name' => 'Company '.$code]);
    $category = Category::query()->create([
        'company_id' => $company->getKey(), 'code' => 'CAT', 'name' => 'Kategori', 'is_active' => true,
    ]);
    $unit = Unit::query()->create([
        'company_id' => $company->getKey(), 'code' => 'ADET', 'name' => 'Adet', 'is_active' => true,
    ]);
    $tax = Tax::query()->create([
        'company_id' => $company->getKey(), 'code' => 'KDV20', 'name' => 'KDV %20', 'rate' => '20.000000', 'is_active' => true,
    ]);

    return [$company, $category, $unit, $tax];
}

function m62Product(
    Company $company,
    Category $category,
    Unit $unit,
    Tax $tax,
    string $code,
    string $name,
    ProductStatus $status = ProductStatus::Active,
): Product {
    return Product::query()->create([
        'company_id' => $company->getKey(),
        'code' => $code,
        'status' => $status,
        'name' => $name,
        'category_id' => $category->getKey(),
        'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(),
        'sale_price_net' => '100.000000',
        'purchase_price_net' => '60.000000',
    ]);
}

/** @param list<PermissionKey> $permissions */
function m62Actor(Company $company, array $permissions, string $suffix): User
{
    $user = User::query()->create([
        'name' => 'M62 '.$suffix,
        'email' => strtolower((string) $company->code).'-'.$suffix.'@m62.test',
        'password' => 'correct-password',
        'status' => UserStatus::Active,
    ]);
    $membership = CompanyMembership::query()->create([
        'company_id' => $company->getKey(), 'user_id' => $user->getKey(), 'is_active' => true, 'joined_at' => now(),
    ]);
    $role = Role::query()->create([
        'company_id' => $company->getKey(), 'code' => 'M62-'.strtoupper($suffix), 'name' => 'M62 '.$suffix, 'is_active' => true,
    ]);
    foreach ($permissions as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}
