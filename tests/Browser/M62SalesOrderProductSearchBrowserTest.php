<?php

use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Authorization\AssignRoleToMembership;
use App\Modules\Core\Authorization\GrantPermissionToRole;
use App\Modules\Core\Enums\DocumentType;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\DocumentSequence;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\Tax;
use App\Modules\Core\Models\User;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Barcode;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\SalesOrders\Models\SalesOrder;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

it('searches a scanner token and selects an active company SKU during manual order entry', function (): void {
    $company = Company::query()->create(['code' => 'BROWSER-M62', 'name' => 'Browser M6.2 Company']);
    $user = User::query()->create([
        'name' => 'Browser M62 Order Manager',
        'email' => 'browser-m62@example.test',
        'password' => 'correct-password',
        'status' => UserStatus::Active,
    ]);
    $membership = CompanyMembership::query()->create([
        'company_id' => $company->getKey(), 'user_id' => $user->getKey(), 'is_active' => true, 'joined_at' => now(),
    ]);
    $role = Role::query()->create([
        'company_id' => $company->getKey(), 'code' => 'ORDER-M62', 'name' => 'Sipariş M62', 'is_active' => true,
    ]);
    app(GrantPermissionToRole::class)->handle($role, PermissionKey::SalesOrderView);
    app(GrantPermissionToRole::class)->handle($role, PermissionKey::SalesOrderManage);
    app(AssignRoleToMembership::class)->handle($membership, $role);

    $account = Account::query()->create([
        'company_id' => $company->getKey(), 'code' => 'CUST', 'type' => AccountType::Customer, 'status' => AccountStatus::Active,
        'legal_name' => 'Browser M62 Müşteri', 'trade_name' => null, 'tax_identity_type' => TaxIdentityType::None,
        'tax_number' => null, 'tax_office' => null, 'book_currency_code' => 'TRY', 'due_days' => 0,
        'discount_rate' => '0.000000', 'risk_limit' => '0.000000',
    ]);
    $category = Category::query()->create([
        'company_id' => $company->getKey(), 'code' => 'CAT', 'name' => 'Kategori', 'is_active' => true,
    ]);
    $unit = Unit::query()->create([
        'company_id' => $company->getKey(), 'code' => 'ADET', 'name' => 'Adet', 'is_active' => true,
    ]);
    $tax = Tax::query()->create([
        'company_id' => $company->getKey(), 'code' => 'KDV20', 'name' => 'KDV %20', 'rate' => '20.000000', 'is_active' => true,
    ]);
    $product = Product::query()->create([
        'company_id' => $company->getKey(), 'code' => 'BROWSER-M62-SKU', 'status' => ProductStatus::Active,
        'name' => 'Aranan Sipariş Ürünü', 'category_id' => $category->getKey(), 'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(), 'sale_price_net' => '125.000000', 'purchase_price_net' => '70.000000',
    ]);
    Barcode::query()->create([
        'company_id' => $company->getKey(), 'product_id' => $product->getKey(), 'barcode' => 'QR-M62-0001', 'is_primary' => true,
    ]);
    DocumentSequence::query()->create([
        'company_id' => $company->getKey(), 'document_type' => DocumentType::SalesOrder->value,
        'series_code' => 'default', 'prefix' => 'SO-', 'padding' => 4, 'next_value' => 1, 'is_active' => true,
    ]);

    expect($user->can(PermissionKey::ProductView->value))->toBeFalse();

    $page = visit('/login')
        ->fill('email', 'browser-m62@example.test')
        ->fill('password', 'correct-password')
        ->click('Giriş Yap')
        ->click('Satış')
        ->click('Yeni Sipariş')
        ->assertPathIs('/sales-orders/create')
        ->select('account_id', (string) $account->getKey())
        ->fill('order_date', '2026-08-26')
        ->select('currency_code', 'TRY')
        ->fill('document_discount_rate', '0')
        ->fill('[data-product-search-input]', 'QR-M62-0001')
        ->click('BROWSER-M62-SKU — Aranan Sipariş Ürünü')
        ->assertValue('[name="lines[0][product_id]"]', (string) $product->getKey())
        ->assertValue('[name="lines[0][unit_price]"]', '125.000000')
        ->fill('[name="lines[0][quantity]"]', '2')
        ->select('[name="lines[0][price_basis]"]', 'net')
        ->fill('[name="lines[0][line_discount_rate]"]', '0')
        ->click('Kaydet')
        ->assertSee('SO-0001')
        ->assertSee('Aranan Sipariş Ürünü')
        ->assertSee('250.000000')
        ->assertSee('300.000000')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();

    $order = SalesOrder::query()->where('company_id', $company->getKey())->firstOrFail();
    expect((string) $order->net_total)->toBe('250.000000')
        ->and((string) $order->gross_total)->toBe('300.000000');
});
