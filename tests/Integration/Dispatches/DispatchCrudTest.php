<?php

use App\Modules\Accounts\Enums\AccountAddressType;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountAddress;
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
use App\Modules\Dispatches\Models\Dispatch;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\SalesOrders\Models\SalesOrder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('creates a numbered draft dispatch with immutable order/address snapshots and no stock or progress side effects', function (): void {
    [$company, $account, $product, $address] = dispatch71Fixture('DSP71-A');
    $manager = dispatch71Actor($company, [
        PermissionKey::SalesOrderView,
        PermissionKey::SalesOrderManage,
        PermissionKey::DispatchView,
        PermissionKey::DispatchManage,
    ], 'manager');
    $order = dispatch71CreateOrder($this, $company, $manager, $account, $product);
    $orderLine = $order->lines()->firstOrFail();

    $stockBefore = DB::table('stock_movements')->count();
    $progressBefore = DB::table('sales_order_line_progress_effects')->count();

    $response = $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/dispatches', [
            'series_code' => 'default',
            'sales_order_id' => $order->getKey(),
            'source_address_id' => $address->getKey(),
            'dispatch_date' => '2026-08-26',
            'carrier_name' => 'Mars Kargo',
            'carrier_service' => 'Ekspres',
            'tracking_number' => 'TRK-71-A',
            'note' => 'Kapıda teslim',
            'lines' => [[
                'sales_order_line_id' => $orderLine->getKey(),
                'quantity' => '1.500000',
            ]],
        ]);

    $dispatch = Dispatch::query()->where('company_id', $company->getKey())->firstOrFail();
    $line = $dispatch->lines()->firstOrFail();
    $response->assertRedirect('/dispatches/'.$dispatch->getKey());

    expect((string) $dispatch->number)->toBe('DSP-0001')
        ->and((int) $dispatch->sales_order_id)->toBe((int) $order->getKey())
        ->and((int) $dispatch->account_id)->toBe((int) $account->getKey())
        ->and((int) $dispatch->source_address_id)->toBe((int) $address->getKey())
        ->and((string) $dispatch->address_line1)->toBe('Mars Cad. 71')
        ->and((string) $dispatch->city)->toBe('İstanbul')
        ->and((string) $dispatch->carrier_name)->toBe('Mars Kargo')
        ->and((string) $dispatch->tracking_number)->toBe('TRK-71-A')
        ->and((int) $line->sales_order_line_id)->toBe((int) $orderLine->getKey())
        ->and((int) $line->product_id)->toBe((int) $product->getKey())
        ->and((string) $line->product_code)->toBe('SKU')
        ->and((string) $line->product_name)->toBe('Ürün DSP71-A')
        ->and((string) $line->quantity)->toBe('1.500000')
        ->and(DB::table('stock_movements')->count())->toBe($stockBefore)
        ->and(DB::table('sales_order_line_progress_effects')->count())->toBe($progressBefore);

    $address->forceFill(['line1' => 'Değişen Adres 99', 'city' => 'Ankara'])->save();
    $product->forceFill(['name' => 'Yeni Ürün Adı'])->save();
    $dispatch->refresh();
    $line->refresh();

    expect((string) $dispatch->address_line1)->toBe('Mars Cad. 71')
        ->and((string) $dispatch->city)->toBe('İstanbul')
        ->and((string) $line->product_name)->toBe('Ürün DSP71-A');

    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->get('/dispatches')->assertOk()->assertSee('DSP-0001')->assertSee('SO-0001');
    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->get('/dispatches/'.$dispatch->getKey())->assertOk()->assertSee('TRK-71-A')->assertSee('Mars Cad. 71');
});

it('enforces dispatch order account and line lineage at PostgreSQL', function (): void {
    [$company, $account, $product, $address] = dispatch71Fixture('DSP71-DB');
    $manager = dispatch71Actor($company, [
        PermissionKey::SalesOrderView,
        PermissionKey::SalesOrderManage,
        PermissionKey::DispatchView,
        PermissionKey::DispatchManage,
    ], 'db-manager');
    $orderA = dispatch71CreateOrder($this, $company, $manager, $account, $product);
    $orderB = dispatch71CreateOrder($this, $company, $manager, $account, $product);
    $lineA = $orderA->lines()->firstOrFail();
    $lineB = $orderB->lines()->firstOrFail();

    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])->post('/dispatches', [
        'series_code' => 'default', 'sales_order_id' => $orderA->getKey(), 'source_address_id' => $address->getKey(),
        'dispatch_date' => '2026-08-26', 'lines' => [[
            'sales_order_line_id' => $lineA->getKey(), 'quantity' => '1',
        ]],
    ])->assertRedirect();
    $dispatch = Dispatch::query()->firstOrFail();

    $otherAccount = dispatch71CreateAccount($company, 'ALT', 'Alternatif Cari');
    $otherAddress = AccountAddress::query()->create([
        'company_id' => $company->getKey(), 'account_id' => $otherAccount->getKey(),
        'type' => AccountAddressType::Shipping, 'label' => 'Alternatif Sevk', 'recipient_name' => 'Alternatif',
        'line1' => 'Başka Cad. 1', 'line2' => null, 'district' => 'Kadıköy', 'city' => 'İstanbul',
        'postal_code' => '34000', 'country_code' => 'TR', 'is_default' => true,
    ]);

    expect(fn () => DB::table('dispatches')->insert([
        'company_id' => $company->getKey(), 'account_id' => $otherAccount->getKey(),
        'sales_order_id' => $orderA->getKey(), 'source_address_id' => $otherAddress->getKey(),
        'number' => 'DSP-HACK', 'series_code' => 'default', 'sequence_value' => 999, 'status' => 'draft',
        'dispatch_date' => '2026-08-26', 'recipient_name' => 'Alternatif', 'address_line1' => 'Başka Cad. 1',
        'address_line2' => null, 'district' => 'Kadıköy', 'city' => 'İstanbul', 'postal_code' => '34000',
        'country_code' => 'TR', 'carrier_name' => null, 'carrier_service' => null, 'tracking_number' => null,
        'note' => null, 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table('dispatch_lines')->insert([
        'company_id' => $company->getKey(), 'dispatch_id' => $dispatch->getKey(),
        'sales_order_id' => $orderB->getKey(), 'sales_order_line_id' => $lineB->getKey(), 'position' => 2,
        'product_id' => $lineB->product_id, 'warehouse_id' => null, 'location_id' => null,
        'product_code' => $lineB->product_code, 'product_name' => $lineB->product_name,
        'description' => $lineB->description, 'quantity' => '1.000000', 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('isolates dispatches by company and enforces view/manage permissions including the sales landing route', function (): void {
    [$companyA] = dispatch71Fixture('DSP71-C-A');
    [$companyB, $accountB, $productB, $addressB] = dispatch71Fixture('DSP71-C-B');
    $managerA = dispatch71Actor($companyA, [PermissionKey::DispatchView, PermissionKey::DispatchManage], 'manager-a');
    $viewerA = dispatch71Actor($companyA, [PermissionKey::DispatchView], 'viewer-a');
    $noDispatchA = dispatch71Actor($companyA, [PermissionKey::AccountView], 'no-dispatch-a');
    $managerB = dispatch71Actor($companyB, [
        PermissionKey::SalesOrderView,
        PermissionKey::SalesOrderManage,
        PermissionKey::DispatchView,
        PermissionKey::DispatchManage,
    ], 'manager-b');

    $orderB = dispatch71CreateOrder($this, $companyB, $managerB, $accountB, $productB);
    $lineB = $orderB->lines()->firstOrFail();
    $this->actingAs($managerB)->withSession(['active_company_id' => $companyB->getKey()])->post('/dispatches', [
        'series_code' => 'default', 'sales_order_id' => $orderB->getKey(), 'source_address_id' => $addressB->getKey(),
        'dispatch_date' => '2026-08-26', 'lines' => [[
            'sales_order_line_id' => $lineB->getKey(), 'quantity' => '1',
        ]],
    ])->assertRedirect();
    $foreign = Dispatch::query()->where('company_id', $companyB->getKey())->firstOrFail();

    $this->actingAs($managerA)->withSession(['active_company_id' => $companyA->getKey()])
        ->post('/dispatches', [
            'series_code' => 'default', 'sales_order_id' => $orderB->getKey(), 'source_address_id' => $addressB->getKey(),
            'dispatch_date' => '2026-08-26', 'lines' => [[
                'sales_order_line_id' => $lineB->getKey(), 'quantity' => '1',
            ]],
        ])->assertSessionHasErrors('sales_order_id');

    $this->actingAs($viewerA)->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/dispatches')->assertOk()->assertDontSee((string) $foreign->number);
    $this->actingAs($viewerA)->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/dispatches/'.$foreign->getKey())->assertNotFound();
    $this->actingAs($viewerA)->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/dispatches/create')->assertForbidden();
    $this->actingAs($viewerA)->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/sales')->assertRedirect('/dispatches');
    $this->actingAs($viewerA)->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/workspace')->assertOk()->assertSee('Satış');
    $this->actingAs($noDispatchA)->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/dispatches')->assertForbidden();
});

/** @return array{Company, Account, Product, AccountAddress} */
function dispatch71Fixture(string $code): array
{
    $company = Company::query()->create(['code' => $code, 'name' => 'Company '.$code]);
    $account = dispatch71CreateAccount($company, 'CUST', 'Müşteri '.$code);
    $category = Category::query()->create(['company_id' => $company->getKey(), 'code' => 'CAT', 'name' => 'Kategori', 'is_active' => true]);
    $unit = Unit::query()->create(['company_id' => $company->getKey(), 'code' => 'ADET', 'name' => 'Adet', 'is_active' => true]);
    $tax = Tax::query()->create(['company_id' => $company->getKey(), 'code' => 'KDV20', 'name' => 'KDV %20', 'rate' => '20.000000', 'is_active' => true]);
    $product = Product::query()->create([
        'company_id' => $company->getKey(), 'code' => 'SKU', 'status' => ProductStatus::Active, 'name' => 'Ürün '.$code,
        'category_id' => $category->getKey(), 'unit_id' => $unit->getKey(), 'tax_id' => $tax->getKey(),
        'sale_price_net' => '100.000000', 'purchase_price_net' => '60.000000',
    ]);
    $address = AccountAddress::query()->create([
        'company_id' => $company->getKey(), 'account_id' => $account->getKey(),
        'type' => AccountAddressType::Shipping, 'label' => 'Ana Sevk', 'recipient_name' => 'Depo Teslim',
        'line1' => 'Mars Cad. 71', 'line2' => 'Kat 1', 'district' => 'Şişli', 'city' => 'İstanbul',
        'postal_code' => '34360', 'country_code' => 'TR', 'is_default' => true,
    ]);
    DocumentSequence::query()->create([
        'company_id' => $company->getKey(), 'document_type' => DocumentType::SalesOrder, 'series_code' => 'default',
        'prefix' => 'SO-', 'padding' => 4, 'next_value' => 1, 'is_active' => true,
    ]);
    DocumentSequence::query()->create([
        'company_id' => $company->getKey(), 'document_type' => DocumentType::Dispatch, 'series_code' => 'default',
        'prefix' => 'DSP-', 'padding' => 4, 'next_value' => 1, 'is_active' => true,
    ]);

    return [$company, $account, $product, $address];
}

function dispatch71CreateAccount(Company $company, string $code, string $name): Account
{
    return Account::query()->create([
        'company_id' => $company->getKey(), 'code' => $code, 'type' => AccountType::Customer, 'status' => AccountStatus::Active,
        'legal_name' => $name, 'trade_name' => null, 'tax_identity_type' => TaxIdentityType::None,
        'tax_number' => null, 'tax_office' => null, 'book_currency_code' => 'TRY', 'due_days' => 0,
        'discount_rate' => '0.000000', 'risk_limit' => '0.000000',
    ]);
}

function dispatch71CreateOrder(TestCase $test, Company $company, User $actor, Account $account, Product $product): SalesOrder
{
    $test->actingAs($actor)->withSession(['active_company_id' => $company->getKey()])->post('/sales-orders', [
        'series_code' => 'default', 'account_id' => $account->getKey(), 'order_date' => '2026-08-26',
        'currency_code' => 'TRY', 'document_discount_rate' => '0', 'note' => null,
        'lines' => [[
            'product_id' => $product->getKey(), 'description' => 'Sevk edilecek ürün', 'quantity' => '3',
            'unit_price' => '100', 'price_basis' => 'net', 'line_discount_rate' => '0', 'tax_zero_reason_id' => null,
        ]],
    ])->assertRedirect();

    return SalesOrder::query()->where('company_id', $company->getKey())->orderByDesc('id')->firstOrFail();
}

/** @param list<PermissionKey> $permissions */
function dispatch71Actor(Company $company, array $permissions, string $suffix): User
{
    $user = User::query()->create([
        'name' => 'Dispatch '.$suffix, 'email' => strtolower((string) $company->code).'-'.$suffix.'@dispatch.test',
        'password' => 'correct-password', 'status' => UserStatus::Active,
    ]);
    $membership = CompanyMembership::query()->create([
        'company_id' => $company->getKey(), 'user_id' => $user->getKey(), 'is_active' => true, 'joined_at' => now(),
    ]);
    $role = Role::query()->create([
        'company_id' => $company->getKey(), 'code' => 'dispatch-'.$suffix, 'name' => 'Dispatch '.$suffix, 'is_active' => true,
    ]);
    foreach ($permissions as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}
