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
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\SalesOrders\Models\SalesOrder;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

it('creates, opens, and edits a manual sales order through the browser without client-side authority', function (): void {
    $company = Company::query()->create(['code' => 'BROWSER-M61', 'name' => 'Browser M6.1 Company']);
    $user = User::query()->create([
        'name' => 'Browser Order Manager',
        'email' => 'browser-m61@example.test',
        'password' => 'correct-password',
        'status' => UserStatus::Active,
    ]);
    $membership = CompanyMembership::query()->create([
        'company_id' => $company->getKey(), 'user_id' => $user->getKey(), 'is_active' => true, 'joined_at' => now(),
    ]);
    $role = Role::query()->create([
        'company_id' => $company->getKey(), 'code' => 'ORDER-MANAGER', 'name' => 'Sipariş Yöneticisi', 'is_active' => true,
    ]);
    app(GrantPermissionToRole::class)->handle($role, PermissionKey::SalesOrderView);
    app(GrantPermissionToRole::class)->handle($role, PermissionKey::SalesOrderManage);
    app(AssignRoleToMembership::class)->handle($membership, $role);

    $account = Account::query()->create([
        'company_id' => $company->getKey(), 'code' => 'CUST', 'type' => AccountType::Customer, 'status' => AccountStatus::Active,
        'legal_name' => 'Browser Sipariş Müşterisi', 'trade_name' => null, 'tax_identity_type' => TaxIdentityType::None,
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
        'company_id' => $company->getKey(), 'code' => 'BROWSER-M61-SKU', 'status' => ProductStatus::Active,
        'name' => 'Browser Sipariş Ürünü', 'category_id' => $category->getKey(), 'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(), 'sale_price_net' => '100.000000', 'purchase_price_net' => '60.000000',
    ]);
    DocumentSequence::query()->create([
        'company_id' => $company->getKey(), 'document_type' => DocumentType::SalesOrder->value,
        'series_code' => 'default', 'prefix' => 'SO-', 'padding' => 4, 'next_value' => 1, 'is_active' => true,
    ]);

    $page = visit('/login')
        ->fill('email', 'browser-m61@example.test')
        ->fill('password', 'correct-password')
        ->click('Giriş Yap')
        ->assertPathIs('/workspace')
        ->assertSee('Satış')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();

    $page->click('Satış')
        ->assertPathIs('/sales-orders')
        ->assertSee('Satış Siparişleri')
        ->click('Yeni Sipariş')
        ->assertPathIs('/sales-orders/create')
        ->select('account_id', (string) $account->getKey())
        ->fill('order_date', '2026-08-26')
        ->select('currency_code', 'TRY')
        ->fill('document_discount_rate', '10')
        ->select('[name="lines[0][product_id]"]', (string) $product->getKey())
        ->fill('[name="lines[0][description]"]', 'Browser order line')
        ->fill('[name="lines[0][quantity]"]', '2')
        ->fill('[name="lines[0][unit_price]"]', '100')
        ->select('[name="lines[0][price_basis]"]', 'net')
        ->fill('[name="lines[0][line_discount_rate]"]', '0')
        ->click('Kaydet')
        ->assertSee('SO-0001')
        ->assertSee('Browser Sipariş Ürünü')
        ->assertSee('180.000000')
        ->assertSee('216.000000')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();

    $order = SalesOrder::query()->where('company_id', $company->getKey())->firstOrFail();
    $page->assertPathIs('/sales-orders/'.$order->getKey())
        ->click('Düzenle')
        ->assertPathIs('/sales-orders/'.$order->getKey().'/edit')
        ->fill('[name="lines[0][quantity]"]', '3')
        ->fill('document_discount_rate', '0')
        ->click('Kaydet')
        ->assertPathIs('/sales-orders/'.$order->getKey())
        ->assertSee('300.000000')
        ->assertSee('360.000000')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();

    expect((string) $order->refresh()->number)->toBe('SO-0001')
        ->and((string) $order->net_total)->toBe('300.000000');
});
