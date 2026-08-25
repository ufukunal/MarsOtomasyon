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
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('ranks an exact SKU ahead of weaker text matches', function (): void {
    [$company, $category, $unit, $tax] = m35SearchCatalog('M35-RANK');
    $viewer = m35SearchActor($company, 'rank');
    m35SearchProduct($company, $category, $unit, $tax, 'AVZ-100', 'Klasik Sarkıt');
    m35SearchProduct($company, $category, $unit, $tax, 'OTHER-1', 'AVZ 100 Dekoratif');

    $response = $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/inventory?q=AVZ-100');

    $response->assertOk()
        ->assertSee('Klasik Sarkıt')
        ->assertSee('AVZ 100 Dekoratif');

    $content = $response->getContent();
    $exactPosition = strpos($content, 'Klasik Sarkıt');
    $textPosition = strpos($content, 'AVZ 100 Dekoratif');

    expect($exactPosition)->not->toBeFalse()
        ->and($textPosition)->not->toBeFalse()
        ->and($exactPosition)->toBeLessThan($textPosition);
});

it('finds a product by exact or partial barcode', function (): void {
    [$company, $category, $unit, $tax] = m35SearchCatalog('M35-BARCODE');
    $viewer = m35SearchActor($company, 'barcode');
    $product = m35SearchProduct($company, $category, $unit, $tax, 'BARCODE-SKU', 'Barkodlu Avize');
    Barcode::query()->create([
        'company_id' => $company->getKey(),
        'product_id' => $product->getKey(),
        'barcode' => '8691234567890',
        'is_primary' => true,
    ]);

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/inventory?q=8691234567890')
        ->assertOk()
        ->assertSee('Barkodlu Avize');

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/inventory?q=456789')
        ->assertOk()
        ->assertSee('Barkodlu Avize');
});

it('uses trigram word similarity for small product-name typos', function (): void {
    [$company, $category, $unit, $tax] = m35SearchCatalog('M35-TYPO');
    $viewer = m35SearchActor($company, 'typo');
    m35SearchProduct($company, $category, $unit, $tax, 'KR-01', 'Kristal Avize Premium');

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/inventory?q=avizze')
        ->assertOk()
        ->assertSee('Kristal Avize Premium');
});

it('keeps product search company scoped and composes with status filtering', function (): void {
    [$companyA, $categoryA, $unitA, $taxA] = m35SearchCatalog('M35-SCOPE-A');
    [$companyB, $categoryB, $unitB, $taxB] = m35SearchCatalog('M35-SCOPE-B');
    $viewer = m35SearchActor($companyA, 'scope');

    m35SearchProduct($companyA, $categoryA, $unitA, $taxA, 'LAMP-ACTIVE', 'Aranan Aktif Ürün');
    m35SearchProduct($companyA, $categoryA, $unitA, $taxA, 'LAMP-INACTIVE', 'Aranan Pasif Ürün', ProductStatus::Inactive);
    m35SearchProduct($companyB, $categoryB, $unitB, $taxB, 'LAMP-FOREIGN', 'Aranan Yabancı Ürün');

    $this->actingAs($viewer)
        ->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/inventory?q=Aranan&status=inactive')
        ->assertOk()
        ->assertSee('Aranan Pasif Ürün')
        ->assertDontSee('Aranan Aktif Ürün')
        ->assertDontSee('Aranan Yabancı Ürün');
});

it('creates the PostgreSQL FTS and trigram indexes used by product search', function (): void {
    $rows = DB::select(<<<'SQL'
        SELECT indexname
        FROM pg_indexes
        WHERE schemaname = current_schema()
          AND tablename IN ('products', 'barcodes')
        SQL);

    $indexes = array_map(
        static fn (object $row): string => (string) $row->indexname,
        $rows,
    );

    expect($indexes)
        ->toContain('products_search_vector_gin')
        ->toContain('products_name_trgm_gin')
        ->toContain('products_code_trgm_gin')
        ->toContain('barcodes_value_trgm_gin');
});

/** @return array{Company, Category, Unit, Tax} */
function m35SearchCatalog(string $code): array
{
    $company = Company::query()->create([
        'code' => $code,
        'name' => 'Company '.$code,
    ]);
    $category = Category::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'LIGHTING',
        'name' => 'Aydınlatma',
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

function m35SearchActor(Company $company, string $suffix): User
{
    $user = User::query()->create([
        'name' => 'M3.5 '.$suffix,
        'email' => strtolower((string) $company->code).'-'.$suffix.'@m35-search.test',
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
        'code' => 'm35-'.$suffix,
        'name' => 'M3.5 '.$suffix,
        'is_active' => true,
    ]);
    app(GrantPermissionToRole::class)->handle($role, PermissionKey::ProductView);
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}

function m35SearchProduct(
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
