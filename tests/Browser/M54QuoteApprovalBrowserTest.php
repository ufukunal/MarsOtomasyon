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
use App\Modules\Quotes\Enums\QuoteStatus;
use App\Modules\Quotes\Models\Quote;
use App\Modules\Quotes\Models\QuoteRevision;
use App\Modules\Quotes\Pricing\PriceBasis;
use App\Modules\SalesOrders\Models\SalesOrder;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

it('approves an immutable revision and converts it to an order through the browser', function (): void {
    $company = Company::query()->create(['code' => 'BROWSER-M54', 'name' => 'Browser M5.4 Company']);
    $user = User::query()->create([
        'name' => 'Browser Quote Approver',
        'email' => 'browser-m54@example.test',
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
        'code' => 'QUOTE-APPROVER',
        'name' => 'Teklif Onay Yöneticisi',
        'is_active' => true,
    ]);
    app(GrantPermissionToRole::class)->handle($role, PermissionKey::QuoteView);
    app(GrantPermissionToRole::class)->handle($role, PermissionKey::QuoteManage);
    app(GrantPermissionToRole::class)->handle($role, PermissionKey::QuoteApprove);
    app(AssignRoleToMembership::class)->handle($membership, $role);

    $account = Account::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'CUST',
        'type' => AccountType::Customer,
        'status' => AccountStatus::Active,
        'legal_name' => 'Browser Müşteri',
        'trade_name' => null,
        'tax_identity_type' => TaxIdentityType::None,
        'tax_number' => null,
        'tax_office' => null,
        'book_currency_code' => 'TRY',
        'due_days' => 0,
        'discount_rate' => '0.000000',
        'risk_limit' => '0.000000',
    ]);
    $category = Category::query()->create([
        'company_id' => $company->getKey(), 'code' => 'CAT', 'name' => 'Kategori', 'is_active' => true,
    ]);
    $unit = Unit::query()->create([
        'company_id' => $company->getKey(), 'code' => 'ADET', 'name' => 'Adet', 'is_active' => true,
    ]);
    $tax = Tax::query()->create([
        'company_id' => $company->getKey(), 'code' => 'KDV20', 'name' => 'KDV %20',
        'rate' => '20.000000', 'is_active' => true,
    ]);
    $product = Product::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'BROWSER-M54-SKU',
        'status' => ProductStatus::Active,
        'name' => 'Browser Teklif Ürünü',
        'category_id' => $category->getKey(),
        'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(),
        'sale_price_net' => '100.000000',
        'purchase_price_net' => '60.000000',
    ]);
    $quote = Quote::query()->create([
        'company_id' => $company->getKey(),
        'account_id' => $account->getKey(),
        'number' => 'Q-BROWSER-0001',
        'series_code' => 'default',
        'sequence_value' => 1,
        'status' => QuoteStatus::Draft->value,
        'quote_date' => '2026-08-26',
        'valid_until' => '2026-09-02',
        'currency_code' => 'TRY',
        'document_discount_rate' => '0.000000',
        'base_net_total' => '100.000000',
        'line_discount_total' => '0.000000',
        'document_discount_total' => '0.000000',
        'net_total' => '100.000000',
        'tax_total' => '20.000000',
        'gross_total' => '120.000000',
        'note' => 'Browser approval',
    ]);
    $quote->lines()->create([
        'company_id' => $company->getKey(),
        'position' => 1,
        'product_id' => $product->getKey(),
        'product_code' => 'BROWSER-M54-SKU',
        'description' => 'Browser teklif satırı',
        'quantity' => '1.000000',
        'price_basis' => PriceBasis::Net,
        'unit_price' => '100.000000',
        'line_discount_rate' => '0.000000',
        'tax_id' => $tax->getKey(),
        'tax_rate' => '20.000000',
        'tax_zero_reason_id' => null,
        'tax_zero_reason_code' => null,
        'base_net' => '100.000000',
        'line_discount_net' => '0.000000',
        'document_discount_net' => '0.000000',
        'net_total' => '100.000000',
        'tax_total' => '20.000000',
        'gross_total' => '120.000000',
    ]);
    DocumentSequence::query()->create([
        'company_id' => $company->getKey(),
        'document_type' => DocumentType::SalesOrder->value,
        'series_code' => 'default',
        'prefix' => 'SO-',
        'padding' => 4,
        'next_value' => 1,
        'is_active' => true,
    ]);

    $page = visit('/login')
        ->fill('email', 'browser-m54@example.test')
        ->fill('password', 'correct-password')
        ->click('Giriş Yap')
        ->assertPathIs('/workspace');

    $page->navigate('/quotes/'.$quote->getKey())
        ->assertSee('Revizyon Geçmişi')
        ->click('Revizyon Snapshot')
        ->assertSee('Immutable Revision')
        ->assertSee('R1')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();

    $revision = QuoteRevision::query()->where('quote_id', $quote->getKey())->firstOrFail();

    $page->navigate('/quotes/'.$quote->getKey())
        ->assertSee('R1')
        ->click('Onayla')
        ->assertSee('Ticari Otorite')
        ->assertSee('R1 · Onaylı')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs()
        ->click('Siparişe Dönüştür')
        ->assertSee('Siparişe Dönüştü')
        ->assertSee('SO-0001')
        ->assertSee('100.000000')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();

    expect((int) Quote::query()->findOrFail($quote->getKey())->selected_revision_id)
        ->toBe((int) $revision->getKey())
        ->and(SalesOrder::query()->where('source_quote_id', $quote->getKey())->count())
        ->toBe(1);
});
