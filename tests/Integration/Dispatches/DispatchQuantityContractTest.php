<?php

use App\Foundation\Identity\SourceEffectIdentity;
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
use App\Modules\Dispatches\Enums\DispatchStatus;
use App\Modules\Dispatches\Models\Dispatch;
use App\Modules\Dispatches\Models\DispatchOrderLineCapacity;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\SalesOrders\Enums\SalesOrderProgressType;
use App\Modules\SalesOrders\Models\SalesOrder;
use App\Modules\SalesOrders\Models\SalesOrderLine;
use App\Modules\SalesOrders\Models\SalesOrderLineProgressEffect;
use App\Modules\SalesOrders\Progress\SalesOrderProgressService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('projects previous current and remaining quantities across partial draft dispatches without stock or progress effects', function (): void {
    [$company, $account, $product, $address, $warehouse, $location, $manager] = dispatch72Fixture('DSP72-PARTIAL');
    $order = dispatch72CreateOrder($this, $company, $manager, $account, $product, '10');
    $line = $order->lines()->firstOrFail();
    $stockBefore = DB::table('stock_movements')->count();
    $progressBefore = SalesOrderLineProgressEffect::query()->count();

    dispatch72Post($this, $company, $manager, $order, $line, $address, $warehouse, $location, '4')->assertRedirect();

    $first = DispatchOrderLineCapacity::query()->where('sales_order_line_id', $line->getKey())->firstOrFail();
    expect((string) $first->ordered_quantity)->toBe('10.000000')
        ->and((string) $first->net_dispatched_quantity)->toBe('0.000000')
        ->and((string) $first->cancelled_quantity)->toBe('0.000000')
        ->and((string) $first->draft_quantity)->toBe('4.000000')
        ->and((string) $first->previous_quantity)->toBe('4.000000')
        ->and((string) $first->remaining_quantity)->toBe('6.000000');

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->get('/dispatches/create?sales_order_id='.$order->getKey())
        ->assertOk()
        ->assertSee('Önceki')
        ->assertSee('4.000000')
        ->assertSee('6.000000');

    dispatch72Post($this, $company, $manager, $order, $line, $address, $warehouse, $location, '3')->assertRedirect();

    $second = $first->fresh();
    expect((string) $second->draft_quantity)->toBe('7.000000')
        ->and((string) $second->previous_quantity)->toBe('7.000000')
        ->and((string) $second->remaining_quantity)->toBe('3.000000')
        ->and(Dispatch::query()->count())->toBe(2)
        ->and(DB::table('stock_movements')->count())->toBe($stockBefore)
        ->and(SalesOrderLineProgressEffect::query()->count())->toBe($progressBefore);
});

it('blocks over dispatch before numbering and keeps the failed draft transaction atomic', function (): void {
    [$company, $account, $product, $address, $warehouse, $location, $manager] = dispatch72Fixture('DSP72-APP-OVER');
    $order = dispatch72CreateOrder($this, $company, $manager, $account, $product, '5');
    $line = $order->lines()->firstOrFail();

    dispatch72Post($this, $company, $manager, $order, $line, $address, $warehouse, $location, '4')->assertRedirect();
    expect((int) DocumentSequence::query()
        ->where('company_id', $company->getKey())
        ->where('document_type', DocumentType::Dispatch->value)
        ->value('next_value'))->toBe(2);

    dispatch72Post($this, $company, $manager, $order, $line, $address, $warehouse, $location, '2')
        ->assertSessionHasErrors('lines.0.quantity');

    expect(Dispatch::query()->where('company_id', $company->getKey())->count())->toBe(1)
        ->and((int) DocumentSequence::query()
            ->where('company_id', $company->getKey())
            ->where('document_type', DocumentType::Dispatch->value)
            ->value('next_value'))->toBe(2);
});

it('enforces over dispatch at PostgreSQL even when application validation is bypassed', function (): void {
    [$company, $account, $product, $address, $warehouse, $location, $manager] = dispatch72Fixture('DSP72-DB-OVER');
    $order = dispatch72CreateOrder($this, $company, $manager, $account, $product, '5');
    $line = $order->lines()->firstOrFail();

    dispatch72Post($this, $company, $manager, $order, $line, $address, $warehouse, $location, '4')->assertRedirect();
    $rawHeader = dispatch72RawHeader($company, $account, $order, $address, 'DSP-RAW-2', 9002);

    expect(fn () => DB::table('dispatch_lines')->insert(dispatch72RawLine(
        $rawHeader,
        $line,
        $warehouse,
        $location,
        '2.000000',
    )))->toThrow(QueryException::class);

    $capacity = DispatchOrderLineCapacity::query()->where('sales_order_line_id', $line->getKey())->firstOrFail();
    expect((string) $capacity->draft_quantity)->toBe('4.000000')
        ->and((string) $capacity->remaining_quantity)->toBe('1.000000');
});

it('shares capacity with existing sales order dispatch and cancellation progress and blocks conflicting progress', function (): void {
    [$company, $account, $product, $address, $warehouse, $location, $manager] = dispatch72Fixture('DSP72-PROGRESS');
    $order = dispatch72CreateOrder($this, $company, $manager, $account, $product, '10');
    $line = $order->lines()->firstOrFail();
    $progress = app(SalesOrderProgressService::class);

    DB::transaction(fn (): SalesOrderLineProgressEffect => $progress->record(
        dispatch72Identity($company, 'existing-dispatch', 'progress.dispatch'),
        (int) $line->getKey(),
        SalesOrderProgressType::Dispatched,
        '3',
    ));
    DB::transaction(fn (): SalesOrderLineProgressEffect => $progress->record(
        dispatch72Identity($company, 'existing-cancel', 'progress.cancel'),
        (int) $line->getKey(),
        SalesOrderProgressType::Cancelled,
        '2',
    ));

    dispatch72Post($this, $company, $manager, $order, $line, $address, $warehouse, $location, '4')->assertRedirect();

    $capacity = DispatchOrderLineCapacity::query()->where('sales_order_line_id', $line->getKey())->firstOrFail();
    expect((string) $capacity->net_dispatched_quantity)->toBe('3.000000')
        ->and((string) $capacity->cancelled_quantity)->toBe('2.000000')
        ->and((string) $capacity->draft_quantity)->toBe('4.000000')
        ->and((string) $capacity->previous_quantity)->toBe('7.000000')
        ->and((string) $capacity->remaining_quantity)->toBe('1.000000');

    expect(fn () => DB::transaction(fn (): SalesOrderLineProgressEffect => $progress->record(
        dispatch72Identity($company, 'conflicting-cancel', 'progress.cancel'),
        (int) $line->getKey(),
        SalesOrderProgressType::Cancelled,
        '2',
    )))->toThrow(ValidationException::class);

    expect((string) $capacity->fresh()->remaining_quantity)->toBe('1.000000');
});

it('serializes competing draft dispatch quantities on the source order line', function (): void {
    [$company, $account, $product, $address, $warehouse, $location, $manager] = dispatch72Fixture('DSP72-CONCURRENT');
    $order = dispatch72CreateOrder($this, $company, $manager, $account, $product, '5');
    $line = $order->lines()->firstOrFail();
    $headerA = dispatch72RawHeader($company, $account, $order, $address, 'DSP-LOCK-A', 9101);
    $headerB = dispatch72RawHeader($company, $account, $order, $address, 'DSP-LOCK-B', 9102);

    config(['database.connections.pgsql_m72_concurrent' => config('database.connections.pgsql')]);
    DB::purge('pgsql_m72_concurrent');
    $concurrent = DB::connection('pgsql_m72_concurrent');
    $concurrent->statement("SET lock_timeout TO '150ms'");

    DB::beginTransaction();

    try {
        DB::table('dispatch_lines')->insert(dispatch72RawLine($headerA, $line, $warehouse, $location, '4.000000'));

        expect(fn () => $concurrent->table('dispatch_lines')->insert(dispatch72RawLine(
            $headerB,
            $line,
            $warehouse,
            $location,
            '2.000000',
        )))->toThrow(QueryException::class);

        DB::commit();
    } finally {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }

    $concurrent->statement("SET lock_timeout TO '0'");
    expect(fn () => $concurrent->table('dispatch_lines')->insert(dispatch72RawLine(
        $headerB,
        $line,
        $warehouse,
        $location,
        '2.000000',
    )))->toThrow(QueryException::class);

    $capacity = DispatchOrderLineCapacity::query()->where('sales_order_line_id', $line->getKey())->firstOrFail();
    expect((string) $capacity->draft_quantity)->toBe('4.000000')
        ->and((string) $capacity->remaining_quantity)->toBe('1.000000')
        ->and(DB::table('dispatch_lines')->count())->toBe(1);

    DB::disconnect('pgsql_m72_concurrent');
});

/** @return array{Company,Account,Product,AccountAddress,Warehouse,WarehouseLocation,User} */
function dispatch72Fixture(string $code): array
{
    $company = Company::query()->create(['code' => $code, 'name' => 'Company '.$code]);
    $account = Account::query()->create([
        'company_id' => $company->getKey(), 'code' => 'CUST', 'type' => AccountType::Customer,
        'status' => AccountStatus::Active, 'legal_name' => 'Müşteri '.$code, 'trade_name' => null,
        'tax_identity_type' => TaxIdentityType::None, 'tax_number' => null, 'tax_office' => null,
        'book_currency_code' => 'TRY', 'due_days' => 0, 'discount_rate' => '0.000000', 'risk_limit' => '0.000000',
    ]);
    $category = Category::query()->create(['company_id' => $company->getKey(), 'code' => 'CAT', 'name' => 'Kategori', 'is_active' => true]);
    $unit = Unit::query()->create(['company_id' => $company->getKey(), 'code' => 'ADET', 'name' => 'Adet', 'is_active' => true]);
    $tax = Tax::query()->create(['company_id' => $company->getKey(), 'code' => 'KDV20', 'name' => 'KDV %20', 'rate' => '20.000000', 'is_active' => true]);
    $product = Product::query()->create([
        'company_id' => $company->getKey(), 'code' => 'SKU', 'status' => ProductStatus::Active, 'name' => 'Ürün '.$code,
        'category_id' => $category->getKey(), 'unit_id' => $unit->getKey(), 'tax_id' => $tax->getKey(),
        'sale_price_net' => '100.000000', 'purchase_price_net' => '60.000000',
    ]);
    $address = AccountAddress::query()->create([
        'company_id' => $company->getKey(), 'account_id' => $account->getKey(), 'type' => AccountAddressType::Shipping,
        'label' => 'Ana Sevk', 'recipient_name' => 'Depo Teslim', 'line1' => 'Mars Cad. 72', 'line2' => null,
        'district' => 'Şişli', 'city' => 'İstanbul', 'postal_code' => '34360', 'country_code' => 'TR', 'is_default' => true,
    ]);
    $warehouse = Warehouse::query()->create([
        'company_id' => $company->getKey(), 'code' => 'WH', 'name' => 'Ana Depo', 'is_active' => true,
    ]);
    $location = WarehouseLocation::query()->create([
        'company_id' => $company->getKey(), 'warehouse_id' => $warehouse->getKey(), 'code' => 'LOC',
        'name' => 'Ana Konum', 'is_active' => true,
    ]);
    DocumentSequence::query()->create([
        'company_id' => $company->getKey(), 'document_type' => DocumentType::SalesOrder, 'series_code' => 'default',
        'prefix' => 'SO-', 'padding' => 4, 'next_value' => 1, 'is_active' => true,
    ]);
    DocumentSequence::query()->create([
        'company_id' => $company->getKey(), 'document_type' => DocumentType::Dispatch, 'series_code' => 'default',
        'prefix' => 'DSP-', 'padding' => 4, 'next_value' => 1, 'is_active' => true,
    ]);
    $manager = dispatch72Actor($company);

    return [$company, $account, $product, $address, $warehouse, $location, $manager];
}

function dispatch72CreateOrder(
    TestCase $test,
    Company $company,
    User $actor,
    Account $account,
    Product $product,
    string $quantity,
): SalesOrder {
    $test->actingAs($actor)->withSession(['active_company_id' => $company->getKey()])->post('/sales-orders', [
        'series_code' => 'default', 'account_id' => $account->getKey(), 'order_date' => '2026-08-26',
        'currency_code' => 'TRY', 'document_discount_rate' => '0', 'note' => null,
        'lines' => [[
            'product_id' => $product->getKey(), 'description' => 'M7.2 sevk satırı', 'quantity' => $quantity,
            'unit_price' => '100', 'price_basis' => 'net', 'line_discount_rate' => '0', 'tax_zero_reason_id' => null,
        ]],
    ])->assertRedirect();

    return SalesOrder::query()->where('company_id', $company->getKey())->latest('id')->firstOrFail();
}

function dispatch72Post(
    TestCase $test,
    Company $company,
    User $manager,
    SalesOrder $order,
    SalesOrderLine $line,
    AccountAddress $address,
    Warehouse $warehouse,
    WarehouseLocation $location,
    string $quantity,
): Illuminate\Testing\TestResponse {
    return $test->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])->post('/dispatches', [
        'series_code' => 'default', 'sales_order_id' => $order->getKey(), 'source_address_id' => $address->getKey(),
        'dispatch_date' => '2026-08-26', 'lines' => [[
            'sales_order_line_id' => $line->getKey(), 'quantity' => $quantity,
            'allocation_key' => $warehouse->getKey().':'.$location->getKey(),
        ]],
    ]);
}

function dispatch72RawHeader(
    Company $company,
    Account $account,
    SalesOrder $order,
    AccountAddress $address,
    string $number,
    int $sequence,
): Dispatch {
    return Dispatch::query()->create([
        'company_id' => $company->getKey(), 'account_id' => $account->getKey(), 'sales_order_id' => $order->getKey(),
        'source_address_id' => $address->getKey(), 'number' => $number, 'series_code' => 'default',
        'sequence_value' => $sequence, 'status' => DispatchStatus::Draft, 'dispatch_date' => '2026-08-26',
        'recipient_name' => $address->recipient_name, 'address_line1' => $address->line1, 'address_line2' => $address->line2,
        'district' => $address->district, 'city' => $address->city, 'postal_code' => $address->postal_code,
        'country_code' => $address->country_code, 'carrier_name' => null, 'carrier_service' => null,
        'tracking_number' => null, 'note' => null,
    ]);
}

/** @return array<string, mixed> */
function dispatch72RawLine(
    Dispatch $dispatch,
    SalesOrderLine $line,
    Warehouse $warehouse,
    WarehouseLocation $location,
    string $quantity,
): array {
    return [
        'company_id' => $dispatch->company_id, 'dispatch_id' => $dispatch->getKey(),
        'sales_order_id' => $dispatch->sales_order_id, 'sales_order_line_id' => $line->getKey(), 'position' => 1,
        'product_id' => $line->product_id, 'warehouse_id' => $warehouse->getKey(), 'location_id' => $location->getKey(),
        'product_code' => $line->product_code, 'product_name' => $line->product_name, 'description' => $line->description,
        'quantity' => $quantity, 'created_at' => now(), 'updated_at' => now(),
    ];
}

function dispatch72Identity(Company $company, string $sourceId, string $effectType): SourceEffectIdentity
{
    return new SourceEffectIdentity(
        companyId: (int) $company->getKey(),
        sourceType: 'dispatch.quantity-test',
        sourceId: $sourceId,
        effectType: $effectType,
    );
}

function dispatch72Actor(Company $company): User
{
    $user = User::query()->create([
        'name' => 'Dispatch M7.2', 'email' => strtolower((string) $company->code).'@dispatch72.test',
        'password' => 'correct-password', 'status' => UserStatus::Active,
    ]);
    $membership = CompanyMembership::query()->create([
        'company_id' => $company->getKey(), 'user_id' => $user->getKey(), 'is_active' => true, 'joined_at' => now(),
    ]);
    $role = Role::query()->create([
        'company_id' => $company->getKey(), 'code' => 'dispatch-m72', 'name' => 'Dispatch M7.2', 'is_active' => true,
    ]);
    foreach ([
        PermissionKey::SalesOrderView,
        PermissionKey::SalesOrderManage,
        PermissionKey::DispatchView,
        PermissionKey::DispatchManage,
    ] as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}
