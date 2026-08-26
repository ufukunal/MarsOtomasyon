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
use App\Modules\Core\Models\TaxZeroReason;
use App\Modules\Core\Models\User;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\SalesOrders\Models\SalesOrder;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

it('zeroes KDV with an explicit reason through the sales order browser flow', function (): void {
    $company = Company::query()->create(['code' => 'BROWSER-M66', 'name' => 'Browser M6.6 Company']);
    $user = User::query()->create([
        'name' => 'Browser M66 Manager', 'email' => 'browser-m66@example.test',
        'password' => 'correct-password', 'status' => UserStatus::Active,
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
        'legal_name' => 'Browser M66 Müşterisi', 'trade_name' => null, 'tax_identity_type' => TaxIdentityType::None,
        'tax_number' => null, 'tax_office' => null, 'book_currency_code' => 'TRY', 'due_days' => 0,
        'discount_rate' => '0.000000', 'risk_limit' => '0.000000',
    ]);
    $category = Category::query()->create(['company_id' => $company->getKey(), 'code' => 'CAT', 'name' => 'Kategori', 'is_active' => true]);
    $unit = Unit::query()->create(['company_id' => $company->getKey(), 'code' => 'ADET', 'name' => 'Adet', 'is_active' => true]);
    $tax = Tax::query()->create([
        'company_id' => $company->getKey(), 'code' => 'KDV20', 'name' => 'KDV %20', 'rate' => '20.000000', 'is_active' => true,
    ]);
    $reason = TaxZeroReason::query()->create([
        'company_id' => $company->getKey(), 'code' => 'ISTISNA', 'name' => 'İstisna', 'is_active' => true,
    ]);
    $product = Product::query()->create([
        'company_id' => $company->getKey(), 'code' => 'BROWSER-M66-SKU', 'status' => ProductStatus::Active,
        'name' => 'Browser M66 Ürünü', 'category_id' => $category->getKey(), 'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(), 'sale_price_net' => '100.000000', 'purchase_price_net' => '60.000000',
    ]);
    DocumentSequence::query()->create([
        'company_id' => $company->getKey(), 'document_type' => DocumentType::SalesOrder->value,
        'series_code' => 'default', 'prefix' => 'SO-', 'padding' => 4, 'next_value' => 1, 'is_active' => true,
    ]);

    $page = visit('/login')
        ->fill('email', 'browser-m66@example.test')
        ->fill('password', 'correct-password')
        ->click('Giriş Yap')
        ->assertPathIs('/workspace')
        ->click('Satış')
        ->click('Yeni Sipariş')
        ->assertPathIs('/sales-orders/create')
        ->assertSee('KDV Sıfırla')
        ->select('account_id', (string) $account->getKey())
        ->fill('order_date', '2026-08-26')
        ->select('currency_code', 'TRY')
        ->fill('document_discount_rate', '0')
        ->fill('[data-product-search-input]', 'BROWSER-M66-SKU')
        ->click('BROWSER-M66-SKU — Browser M66 Ürünü')
        ->fill('[name="lines[0][quantity]"]', '2')
        ->fill('[name="lines[0][unit_price]"]', '100')
        ->click('[name="lines[0][tax_is_zeroed]"]')
        ->select('[name="lines[0][tax_zero_reason_id]"]', (string) $reason->getKey())
        ->click('Kaydet')
        ->assertSee('KDV Sıfırlandı')
        ->assertSee('Neden: ISTISNA')
        ->assertSee('200.000000')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();

    $order = SalesOrder::query()->where('company_id', $company->getKey())->firstOrFail();
    $line = $order->lines()->firstOrFail();
    expect($line->tax_is_zeroed)->toBeTrue()
        ->and((string) $line->tax_rate)->toBe('0.000000')
        ->and((string) $order->tax_total)->toBe('0.000000')
        ->and((string) $order->gross_total)->toBe('200.000000');
});
