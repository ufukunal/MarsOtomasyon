<?php

use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
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
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductSupplier;
use App\Modules\Products\Models\Unit;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

it('opens the product resources workspace with supplier technical file and media sections without browser errors', function (): void {
    $company = Company::query()->create([
        'code' => 'BROWSER-M34',
        'name' => 'Browser M3.4 Company',
    ]);
    $user = User::query()->create([
        'name' => 'Browser Product Resource Manager',
        'email' => 'browser-m34@example.test',
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
        'code' => 'M34-RESOURCE-MANAGER',
        'name' => 'M3.4 Kaynak Yöneticisi',
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
        'code' => 'M34-CAT',
        'name' => 'M3.4 Kategori',
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
        'code' => 'M34-KDV20',
        'name' => 'KDV %20',
        'rate' => '20.000000',
        'is_active' => true,
    ]);
    $product = Product::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'M34-SKU',
        'status' => ProductStatus::Active,
        'name' => 'M3.4 Avize',
        'category_id' => $category->getKey(),
        'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(),
        'sale_price_net' => '100.000000',
        'purchase_price_net' => '50.000000',
    ]);
    $supplier = Account::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'M34-SUP',
        'type' => AccountType::Supplier,
        'status' => AccountStatus::Active,
        'legal_name' => 'M3.4 Tedarikçi A.Ş.',
        'trade_name' => null,
        'tax_identity_type' => TaxIdentityType::None,
        'tax_number' => null,
        'tax_office' => null,
        'book_currency_code' => 'TRY',
        'due_days' => 0,
        'discount_rate' => '0.000000',
        'risk_limit' => '0.000000',
    ]);
    ProductSupplier::query()->create([
        'company_id' => $company->getKey(),
        'product_id' => $product->getKey(),
        'account_id' => $supplier->getKey(),
    ]);

    $page = visit('/login')
        ->fill('email', 'browser-m34@example.test')
        ->fill('password', 'correct-password')
        ->click('Giriş Yap')
        ->assertPathIs('/workspace')
        ->click('Ürün/Stok')
        ->assertPathIs('/inventory')
        ->assertSee('M3.4 Avize')
        ->click('Detay')
        ->assertPathIs('/inventory/products/'.$product->getKey())
        ->assertSee('Tedarikçi / Dosyalar')
        ->click('Tedarikçi / Dosyalar')
        ->assertPathIs('/inventory/products/'.$product->getKey().'/resources')
        ->assertSee('M3.4 Avize')
        ->assertSee('M34-SUP')
        ->assertSee('M3.4 Tedarikçi A.Ş.')
        ->assertSee('Tedarikçileri Kaydet')
        ->assertSee('Teknik Dosyalar')
        ->assertSee('Teknik Dosya Yükle')
        ->assertSee('Medya')
        ->assertSee('Medya Yükle')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});
