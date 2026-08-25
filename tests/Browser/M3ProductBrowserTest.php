<?php

use App\Modules\Core\Authorization\AssignRoleToMembership;
use App\Modules\Core\Authorization\GrantPermissionToRole;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\Tax;
use App\Modules\Core\Models\User;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

it('drives the V16.3 product list create readonly detail and separate edit flow without browser errors', function (): void {
    $company = Company::query()->create([
        'code' => 'BROWSER-M3',
        'name' => 'Browser M3 Company',
    ]);
    $user = User::query()->create([
        'name' => 'Browser Product Manager',
        'email' => 'browser-m3@example.test',
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
        'code' => 'PRODUCT-MANAGER',
        'name' => 'Ürün Yöneticisi',
        'is_active' => true,
    ]);
    app(GrantPermissionToRole::class)->handle($role, PermissionKey::ProductView);
    app(GrantPermissionToRole::class)->handle($role, PermissionKey::ProductManage);
    app(AssignRoleToMembership::class)->handle($membership, $role);
    Branch::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'MAIN',
        'name' => 'Merkez',
        'is_active' => true,
    ]);
    $category = Category::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'AVIZE',
        'name' => 'Avize',
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

    $page = visit('/login')
        ->fill('email', 'browser-m3@example.test')
        ->fill('password', 'correct-password')
        ->click('Giriş Yap')
        ->assertPathIs('/workspace')
        ->assertSee('Ürün/Stok')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();

    $page->click('Ürün/Stok')
        ->assertPathIs('/inventory')
        ->assertSee('Yeni Ürün')
        ->assertNoJavaScriptErrors();

    $page->click('Yeni Ürün')
        ->assertPathIs('/inventory/products/create')
        ->fill('code', 'BROWSER-SKU-001')
        ->fill('name', 'Browser Avize')
        ->select('category_id', (string) $category->getKey())
        ->select('unit_id', (string) $unit->getKey())
        ->select('tax_id', (string) $tax->getKey())
        ->fill('sale_price_net', '1250.500000')
        ->fill('purchase_price_net', '700.250000')
        ->fill('primary_barcode', '8691234567890')
        ->fill('additional_barcodes', "8691234567891\n8691234567892")
        ->click('Kaydet')
        ->assertSee('Browser Avize')
        ->assertSee('BROWSER-SKU-001')
        ->assertSee('KDV Hariç / Net')
        ->assertSee('8691234567890')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();

    $product = Product::query()->where('company_id', $company->getKey())->firstOrFail();
    $page->assertPathIs('/inventory/products/'.$product->getKey())
        ->assertCount('input', 0)
        ->assertCount('select', 0)
        ->click('Düzenle')
        ->assertPathIs('/inventory/products/'.$product->getKey().'/edit')
        ->fill('name', 'Browser Avize Güncel')
        ->select('status', 'inactive')
        ->fill('sale_price_net', '1350.750000')
        ->click('Kaydet')
        ->assertPathIs('/inventory/products/'.$product->getKey())
        ->assertSee('Browser Avize Güncel')
        ->assertSee('Pasif')
        ->assertSee('1350.750000')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});
