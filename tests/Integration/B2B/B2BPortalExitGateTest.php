<?php

use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountAddress;
use App\Modules\Accounts\Models\AccountB2BPolicy;
use App\Modules\B2B\Enums\B2BPermission;
use App\Modules\B2B\Enums\B2BRiskBehavior;
use App\Modules\B2B\Enums\B2BRole;
use App\Modules\B2B\Enums\B2BUserStatus;
use App\Modules\B2B\Models\B2BUser;
use App\Modules\B2B\Portal\B2BPriceCalculator;
use App\Modules\Core\Enums\DocumentType;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\DocumentSequence;
use App\Modules\Core\Models\Tax;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Ledger\PostStockMovementData;
use App\Modules\Inventory\Ledger\StockMovementPoster;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\SalesOrders\Models\SalesOrder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('uses typed user permission and account policy as a non-escalating permission ceiling', function (): void {
    [, , $policy, $user] = m19PortalFixture('PERM');
    expect($user->hasPermission(B2BPermission::ViewInvoices))->toBeTrue()
        ->and($policy->allows(B2BPermission::ViewInvoices))->toBeTrue();

    $policy->update(['show_invoices' => false]);
    $this->actingAs($user, 'b2b')
        ->withSession(['b2b_auth_version' => $user->auth_version])
        ->get('/b2b/invoices')
        ->assertForbidden();
});

it('calculates B2B net price only from product sale price and account discount', function (): void {
    [, $account, , , $product] = m19PortalFixture('PRICE');
    $product->sale_price_net = '123.456789';
    $account->discount_rate = '12.500000';

    expect(app(B2BPriceCalculator::class)->netPrice($product, $account))->toBe('108.024690');
});

it('keeps B2B address identifiers immutable public ULIDs', function (): void {
    [$company, $account] = m19PortalFixture('ADDR');
    $address = AccountAddress::query()->create([
        'company_id' => $company->getKey(), 'account_id' => $account->getKey(), 'type' => 'shipping',
        'label' => 'Depo', 'line1' => 'Test Cad. 1', 'city' => 'İstanbul', 'country_code' => 'TR', 'is_default' => false,
    ])->refresh();

    expect((string) $address->public_id)->toHaveLength(26)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/');
    expect(fn () => DB::table('account_addresses')->where('id', $address->getKey())->update(['public_id' => (string) Str::ulid()]))
        ->toThrow(QueryException::class);
});

it('revokes an authenticated B2B session when auth version changes', function (): void {
    [, , , $user] = m19PortalFixture('REVOKE');
    $version = (int) $user->auth_version;
    $user->forceFill(['auth_version' => $version + 1])->save();

    $this->actingAs($user, 'b2b')
        ->withSession(['b2b_auth_version' => $version])
        ->get('/b2b')
        ->assertRedirect('/b2b/login')
        ->assertSessionHasErrors('email');
    $this->assertGuest('b2b');
});

it('creates a fixed-account discounted B2B order exactly once and never posts account balance itself', function (): void {
    [$company, $account, , $user, $product, $warehouse, $location] = m19PortalFixture('ORDER');
    m19PortalOpening($company, $product, $warehouse, $location, '10');
    $key = (string) Str::ulid();
    $session = ['b2b_auth_version' => $user->auth_version, 'b2b_cart' => [(string) $product->code => '2.000000']];

    $this->actingAs($user, 'b2b')->withSession($session)
        ->post('/b2b/orders', ['idempotency_key' => $key])
        ->assertRedirect();
    $order = SalesOrder::query()->where('company_id', $company->getKey())->where('account_id', $account->getKey())->firstOrFail();
    $line = $order->lines()->firstOrFail();

    expect((string) $line->unit_price)->toBe('100.000000')
        ->and((string) $line->line_discount_rate)->toBe('10.000000')
        ->and((string) $line->net_total)->toBe('180.000000')
        ->and((string) $order->gross_total)->toBe('216.000000')
        ->and(DB::table('account_transactions')->where('company_id', $company->getKey())->where('account_id', $account->getKey())->count())->toBe(0);

    $this->actingAs($user, 'b2b')->withSession($session)
        ->post('/b2b/orders', ['idempotency_key' => $key])
        ->assertRedirect('/b2b/orders/'.$order->number);
    expect(SalesOrder::query()->where('company_id', $company->getKey())->count())->toBe(1);
});

it('blocks order creation server-side when current exposure plus order exceeds risk limit', function (): void {
    [$company, $account, , $user, $product, $warehouse, $location] = m19PortalFixture('RISK');
    $account->update(['risk_limit' => '100.000000']);
    m19PortalOpening($company, $product, $warehouse, $location, '10');

    $this->actingAs($user, 'b2b')
        ->withSession(['b2b_auth_version' => $user->auth_version, 'b2b_cart' => [(string) $product->code => '2.000000']])
        ->post('/b2b/orders', ['idempotency_key' => (string) Str::ulid()])
        ->assertSessionHasErrors('risk');

    expect(SalesOrder::query()->where('company_id', $company->getKey())->count())->toBe(0);
});

/** @return array{Company, Account, AccountB2BPolicy, B2BUser, Product, Warehouse, WarehouseLocation} */
function m19PortalFixture(string $suffix): array
{
    $company = Company::query()->create(['code' => 'M19-'.$suffix, 'name' => 'M19 '.$suffix]);
    $account = Account::query()->create([
        'company_id' => $company->getKey(), 'code' => 'CUST-'.$suffix, 'type' => AccountType::Customer,
        'status' => AccountStatus::Active, 'legal_name' => 'Bayi '.$suffix, 'trade_name' => null,
        'tax_identity_type' => TaxIdentityType::None, 'tax_number' => null, 'tax_office' => null,
        'book_currency_code' => 'TRY', 'due_days' => 0, 'discount_rate' => '10.000000', 'risk_limit' => '10000.000000',
    ]);
    $category = Category::query()->create(['company_id' => $company->getKey(), 'code' => 'CAT', 'name' => 'Kategori', 'is_active' => true]);
    $unit = Unit::query()->create(['company_id' => $company->getKey(), 'code' => 'ADET', 'name' => 'Adet', 'is_active' => true]);
    $tax = Tax::query()->create(['company_id' => $company->getKey(), 'code' => 'KDV20', 'name' => 'KDV %20', 'rate' => '20.000000', 'is_active' => true]);
    $product = Product::query()->create([
        'company_id' => $company->getKey(), 'code' => 'SKU-'.$suffix, 'status' => ProductStatus::Active,
        'name' => 'Ürün '.$suffix, 'category_id' => $category->getKey(), 'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(), 'sale_price_net' => '100.000000', 'purchase_price_net' => '60.000000',
    ]);
    $warehouse = Warehouse::query()->create(['company_id' => $company->getKey(), 'code' => 'MAIN', 'name' => 'Merkez', 'is_active' => true]);
    $location = WarehouseLocation::query()->create([
        'company_id' => $company->getKey(), 'warehouse_id' => $warehouse->getKey(), 'code' => 'A-01', 'name' => 'A-01', 'is_active' => true,
    ]);
    DocumentSequence::query()->create([
        'company_id' => $company->getKey(), 'document_type' => DocumentType::SalesOrder->value,
        'series_code' => 'b2b', 'prefix' => 'B2B-', 'padding' => 4, 'next_value' => 1, 'is_active' => true,
    ]);
    $policy = AccountB2BPolicy::query()->create([
        'company_id' => $company->getKey(), 'account_id' => $account->getKey(), 'is_enabled' => true,
        'allow_orders' => true, 'show_price' => true, 'show_stock' => true, 'show_balance' => true,
        'show_invoices' => true, 'show_statement' => true, 'allow_address_management' => true,
        'default_warehouse_id' => $warehouse->getKey(), 'risk_behavior' => B2BRiskBehavior::Block,
    ]);
    $user = B2BUser::query()->create([
        'company_id' => $company->getKey(), 'account_id' => $account->getKey(), 'name' => 'B2B '.$suffix,
        'email' => mb_strtolower($suffix).'@b2b.test', 'password' => 'Correct-Password-19', 'status' => B2BUserStatus::Active,
        'role' => B2BRole::Admin, 'permissions' => B2BPermission::values(),
    ])->refresh();

    return [$company, $account, $policy, $user, $product, $warehouse, $location];
}

function m19PortalOpening(Company $company, Product $product, Warehouse $warehouse, WarehouseLocation $location, string $quantity): void
{
    DB::transaction(fn () => app(StockMovementPoster::class)->post(new PostStockMovementData(
        sourceEffect: new SourceEffectIdentity((int) $company->getKey(), 'b2b.exit_gate', 'opening-'.$product->getKey(), 'inventory.opening'),
        productId: (int) $product->getKey(), warehouseId: (int) $warehouse->getKey(), locationId: (int) $location->getKey(),
        movementType: StockMovementType::OpeningIn, quantity: $quantity, unitCost: '10',
    )));
}
