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
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\DocumentType;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\DocumentSequence;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\Tax;
use App\Modules\Core\Models\User;
use App\Modules\Dispatches\Actions\FinalizeDispatch;
use App\Modules\Dispatches\Enums\DispatchStatus;
use App\Modules\Dispatches\Models\Dispatch;
use App\Modules\Dispatches\Models\DispatchOrderLineCapacity;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Ledger\PostStockMovementData;
use App\Modules\Inventory\Ledger\StockMovementPoster;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\SalesOrders\Models\SalesOrder;
use App\Modules\SalesOrders\Models\SalesOrderLineProgress;
use App\Modules\SalesOrders\Models\SalesOrderLineProgressEffect;
use App\Modules\SalesOrders\Models\SalesOrderReservationGeneration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('holds dispatch quantity stock progress reversal reservation and replay invariants together', function (): void {
    [$company, $account, $product, $address, $warehouse, $location, $manager] = dispatch76Fixture();
    dispatch76Opening($company, $product, $warehouse, $location, '6');
    $order = dispatch76Order($this, $company, $manager, $account, $product, $warehouse, $location, '6');
    $orderLine = $order->lines()->firstOrFail();
    $logicalLineKey = (string) $orderLine->logical_line_key;

    $initialGeneration = SalesOrderReservationGeneration::query()
        ->where('sales_order_id', $order->getKey())
        ->where('logical_line_key', $logicalLineKey)
        ->firstOrFail();
    $balance = StockBalance::query()->where('product_id', $product->getKey())->firstOrFail();

    expect((int) $initialGeneration->generation)->toBe(1)
        ->and((string) $initialGeneration->quantity)->toBe('6.000000')
        ->and($initialGeneration->released_at)->toBeNull()
        ->and((string) $balance->quantity)->toBe('6.000000')
        ->and((string) $balance->reserved_quantity)->toBe('6.000000')
        ->and((string) $balance->available_quantity)->toBe('0.000000');

    $first = dispatch76Dispatch($this, $company, $manager, $order, $address, $warehouse, $location, '2');
    $second = dispatch76Dispatch($this, $company, $manager, $order, $address, $warehouse, $location, '2');
    $firstLine = $first->lines()->firstOrFail();
    $secondLine = $second->lines()->firstOrFail();

    $capacity = dispatch76Capacity($orderLine->getKey());
    expect((string) $capacity->ordered_quantity)->toBe('6.000000')
        ->and((string) $capacity->net_dispatched_quantity)->toBe('0.000000')
        ->and((string) $capacity->draft_quantity)->toBe('4.000000')
        ->and((string) $capacity->remaining_quantity)->toBe('2.000000')
        ->and(StockMovement::query()->where('movement_type', StockMovementType::DispatchOut->value)->count())->toBe(0)
        ->and(SalesOrderLineProgressEffect::query()->where('progress_type', 'dispatched')->count())->toBe(0);

    dispatch76Finalize($this, $company, $manager, $first);

    $capacity = dispatch76Capacity($orderLine->getKey());
    $activeGeneration = dispatch76ActiveGeneration($order, $logicalLineKey);
    $balance->refresh();
    expect($first->refresh()->statusEnum())->toBe(DispatchStatus::Finalized)
        ->and((string) $capacity->net_dispatched_quantity)->toBe('2.000000')
        ->and((string) $capacity->draft_quantity)->toBe('2.000000')
        ->and((string) $capacity->remaining_quantity)->toBe('2.000000')
        ->and((int) $activeGeneration->generation)->toBe(2)
        ->and((string) $activeGeneration->quantity)->toBe('4.000000')
        ->and((string) $balance->quantity)->toBe('4.000000')
        ->and((string) $balance->reserved_quantity)->toBe('4.000000');

    dispatch76Finalize($this, $company, $manager, $first);
    expect(StockMovement::query()
        ->where('source_type', 'dispatch_line')
        ->where('source_id', (string) $firstLine->getKey())
        ->where('effect_type', 'stock.out')
        ->count())->toBe(1)
        ->and(SalesOrderLineProgressEffect::query()
            ->where('source_type', 'dispatch_line')
            ->where('source_id', (string) $firstLine->getKey())
            ->where('effect_type', 'progress.dispatch')
            ->count())->toBe(1);

    dispatch76Finalize($this, $company, $manager, $second);

    $capacity = dispatch76Capacity($orderLine->getKey());
    $activeGeneration = dispatch76ActiveGeneration($order, $logicalLineKey);
    $balance->refresh();
    expect($second->refresh()->statusEnum())->toBe(DispatchStatus::Finalized)
        ->and((string) $capacity->net_dispatched_quantity)->toBe('4.000000')
        ->and((string) $capacity->draft_quantity)->toBe('0.000000')
        ->and((string) $capacity->remaining_quantity)->toBe('2.000000')
        ->and((int) $activeGeneration->generation)->toBe(3)
        ->and((string) $activeGeneration->quantity)->toBe('2.000000')
        ->and((string) $balance->quantity)->toBe('2.000000')
        ->and((string) $balance->reserved_quantity)->toBe('2.000000');

    dispatch76Cancel($this, $company, $manager, $first);

    $capacity = dispatch76Capacity($orderLine->getKey());
    $projection = SalesOrderLineProgress::query()
        ->where('sales_order_line_id', $orderLine->getKey())
        ->firstOrFail();
    $activeGeneration = dispatch76ActiveGeneration($order, $logicalLineKey);
    $balance->refresh();
    expect($first->refresh()->statusEnum())->toBe(DispatchStatus::Cancelled)
        ->and($second->refresh()->statusEnum())->toBe(DispatchStatus::Finalized)
        ->and((string) $capacity->net_dispatched_quantity)->toBe('2.000000')
        ->and((string) $capacity->remaining_quantity)->toBe('4.000000')
        ->and((string) $projection->net_dispatched_quantity)->toBe('2.000000')
        ->and((string) $projection->dispatch_remaining_quantity)->toBe('4.000000')
        ->and((int) $activeGeneration->generation)->toBe(4)
        ->and((string) $activeGeneration->quantity)->toBe('4.000000')
        ->and((string) $balance->quantity)->toBe('4.000000')
        ->and((string) $balance->reserved_quantity)->toBe('4.000000');

    dispatch76Cancel($this, $company, $manager, $first);
    expect(StockMovement::query()
        ->where('source_type', 'dispatch_line')
        ->where('source_id', (string) $firstLine->getKey())
        ->count())->toBe(2)
        ->and(SalesOrderLineProgressEffect::query()
            ->where('source_type', 'dispatch_line')
            ->where('source_id', (string) $firstLine->getKey())
            ->count())->toBe(2);

    $third = dispatch76Dispatch($this, $company, $manager, $order, $address, $warehouse, $location, '4');
    $thirdLine = $third->lines()->firstOrFail();
    $capacity = dispatch76Capacity($orderLine->getKey());
    expect((string) $capacity->net_dispatched_quantity)->toBe('2.000000')
        ->and((string) $capacity->draft_quantity)->toBe('4.000000')
        ->and((string) $capacity->remaining_quantity)->toBe('0.000000');

    dispatch76Finalize($this, $company, $manager, $third);
    dispatch76Finalize($this, $company, $manager, $third);

    $capacity = dispatch76Capacity($orderLine->getKey());
    $projection = $projection->fresh();
    $balance->refresh();
    expect($third->refresh()->statusEnum())->toBe(DispatchStatus::Finalized)
        ->and((string) $capacity->net_dispatched_quantity)->toBe('6.000000')
        ->and((string) $capacity->draft_quantity)->toBe('0.000000')
        ->and((string) $capacity->remaining_quantity)->toBe('0.000000')
        ->and((string) $projection->net_dispatched_quantity)->toBe('6.000000')
        ->and((string) $projection->dispatch_remaining_quantity)->toBe('0.000000')
        ->and((string) $balance->quantity)->toBe('0.000000')
        ->and((string) $balance->reserved_quantity)->toBe('0.000000')
        ->and((string) $balance->available_quantity)->toBe('0.000000')
        ->and(SalesOrderReservationGeneration::query()
            ->where('sales_order_id', $order->getKey())
            ->where('logical_line_key', $logicalLineKey)
            ->whereNull('released_at')
            ->count())->toBe(0)
        ->and(SalesOrderReservationGeneration::query()
            ->where('sales_order_id', $order->getKey())
            ->where('logical_line_key', $logicalLineKey)
            ->count())->toBe(4)
        ->and(StockMovement::query()->where('movement_type', StockMovementType::DispatchOut->value)->count())->toBe(3)
        ->and(StockMovement::query()->where('effect_type', 'stock.out.reverse')->count())->toBe(1)
        ->and(SalesOrderLineProgressEffect::query()->where('effect_type', 'progress.dispatch')->count())->toBe(3)
        ->and(SalesOrderLineProgressEffect::query()->where('effect_type', 'progress.dispatch.reverse')->count())->toBe(1)
        ->and(StockMovement::query()
            ->where('source_type', 'dispatch_line')
            ->where('source_id', (string) $thirdLine->getKey())
            ->where('effect_type', 'stock.out')
            ->count())->toBe(1)
        ->and(SalesOrderLineProgressEffect::query()
            ->where('source_type', 'dispatch_line')
            ->where('source_id', (string) $thirdLine->getKey())
            ->where('effect_type', 'progress.dispatch')
            ->count())->toBe(1);

    app(ActiveCompanyContext::class)->set($company);
    expect(fn () => app(FinalizeDispatch::class)->handle((int) $first->getKey()))
        ->toThrow(ValidationException::class);

    expect(fn () => DB::table('dispatches')->where('id', $first->getKey())->update([
        'note' => 'cancelled mutation',
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
    expect(fn () => DB::table('dispatches')->where('id', $second->getKey())->update([
        'note' => 'finalized mutation',
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->get('/sales-orders/'.$order->getKey().'/edit')
        ->assertStatus(409);
    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->get(route('dispatches.show', $first->getKey()))
        ->assertOk()
        ->assertSee('İptal')
        ->assertDontSee('İrsaliyeyi Kesinleştir')
        ->assertDontSee('İrsaliyeyi İptal Et');
});

/** @return array{Company,Account,Product,AccountAddress,Warehouse,WarehouseLocation,User} */
function dispatch76Fixture(): array
{
    $company = Company::query()->create(['code' => 'M76-EXIT', 'name' => 'M7.6 Exit Gate Company']);
    $account = Account::query()->create([
        'company_id' => $company->getKey(), 'code' => 'CUST', 'type' => AccountType::Customer,
        'status' => AccountStatus::Active, 'legal_name' => 'M7.6 Müşterisi', 'trade_name' => null,
        'tax_identity_type' => TaxIdentityType::None, 'tax_number' => null, 'tax_office' => null,
        'book_currency_code' => 'TRY', 'due_days' => 0, 'discount_rate' => '0.000000', 'risk_limit' => '0.000000',
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
        'company_id' => $company->getKey(), 'code' => 'M76-SKU', 'status' => ProductStatus::Active,
        'name' => 'M7.6 Ürünü', 'category_id' => $category->getKey(), 'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(), 'sale_price_net' => '100.000000', 'purchase_price_net' => '60.000000',
    ]);
    $address = AccountAddress::query()->create([
        'company_id' => $company->getKey(), 'account_id' => $account->getKey(), 'type' => AccountAddressType::Shipping,
        'label' => 'Ana Sevk', 'recipient_name' => 'M7.6 Teslim', 'line1' => 'Mars Cad. 76', 'line2' => null,
        'district' => 'Şişli', 'city' => 'İstanbul', 'postal_code' => '34360', 'country_code' => 'TR', 'is_default' => true,
    ]);
    $warehouse = Warehouse::query()->create([
        'company_id' => $company->getKey(), 'code' => 'MAIN', 'name' => 'Merkez Depo', 'is_active' => true,
    ]);
    $location = WarehouseLocation::query()->create([
        'company_id' => $company->getKey(), 'warehouse_id' => $warehouse->getKey(),
        'code' => 'A-01', 'name' => 'A Rafı', 'is_active' => true,
    ]);
    DocumentSequence::query()->create([
        'company_id' => $company->getKey(), 'document_type' => DocumentType::SalesOrder,
        'series_code' => 'default', 'prefix' => 'SO-', 'padding' => 4, 'next_value' => 1, 'is_active' => true,
    ]);
    DocumentSequence::query()->create([
        'company_id' => $company->getKey(), 'document_type' => DocumentType::Dispatch,
        'series_code' => 'default', 'prefix' => 'DSP-', 'padding' => 4, 'next_value' => 1, 'is_active' => true,
    ]);

    $manager = User::query()->create([
        'name' => 'M76 Manager', 'email' => 'm76-manager@example.test',
        'password' => 'correct-password', 'status' => UserStatus::Active,
    ]);
    $membership = CompanyMembership::query()->create([
        'company_id' => $company->getKey(), 'user_id' => $manager->getKey(), 'is_active' => true, 'joined_at' => now(),
    ]);
    $role = Role::query()->create([
        'company_id' => $company->getKey(), 'code' => 'M76', 'name' => 'M7.6 Manager', 'is_active' => true,
    ]);
    foreach ([PermissionKey::SalesOrderView, PermissionKey::SalesOrderManage, PermissionKey::DispatchView, PermissionKey::DispatchManage] as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return [$company, $account, $product, $address, $warehouse, $location, $manager];
}

function dispatch76Opening(Company $company, Product $product, Warehouse $warehouse, WarehouseLocation $location, string $quantity): void
{
    DB::transaction(fn () => app(StockMovementPoster::class)->post(new PostStockMovementData(
        sourceEffect: new SourceEffectIdentity((int) $company->getKey(), 'dispatch.exit_gate', 'opening', 'inventory.opening'),
        productId: (int) $product->getKey(),
        warehouseId: (int) $warehouse->getKey(),
        locationId: (int) $location->getKey(),
        movementType: StockMovementType::OpeningIn,
        quantity: $quantity,
        unitCost: '10',
    )));
}

function dispatch76Order(TestCase $test, Company $company, User $manager, Account $account, Product $product, Warehouse $warehouse, WarehouseLocation $location, string $quantity): SalesOrder
{
    $test->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])->post('/sales-orders', [
        'series_code' => 'default',
        'account_id' => $account->getKey(),
        'order_date' => '2026-08-26',
        'currency_code' => 'TRY',
        'document_discount_rate' => '0',
        'note' => 'M7.6 exit gate',
        'lines' => [[
            'logical_line_key' => null,
            'product_id' => $product->getKey(),
            'warehouse_id' => $warehouse->getKey(),
            'location_id' => $location->getKey(),
            'description' => 'M7.6 line',
            'quantity' => $quantity,
            'unit_price' => '100',
            'price_basis' => 'net',
            'line_discount_rate' => '0',
            'tax_zero_reason_id' => null,
        ]],
    ])->assertRedirect();

    return SalesOrder::query()->where('company_id', $company->getKey())->latest('id')->firstOrFail();
}

function dispatch76Dispatch(TestCase $test, Company $company, User $manager, SalesOrder $order, AccountAddress $address, Warehouse $warehouse, WarehouseLocation $location, string $quantity): Dispatch
{
    $line = $order->lines()->firstOrFail();
    $test->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])->post('/dispatches', [
        'series_code' => 'default',
        'sales_order_id' => $order->getKey(),
        'source_address_id' => $address->getKey(),
        'dispatch_date' => '2026-08-26',
        'carrier_name' => 'Mars Lojistik',
        'carrier_service' => 'Standart',
        'tracking_number' => null,
        'note' => 'M7.6 dispatch',
        'lines' => [[
            'sales_order_line_id' => $line->getKey(),
            'quantity' => $quantity,
            'allocation_key' => $warehouse->getKey().':'.$location->getKey(),
        ]],
    ])->assertRedirect();

    return Dispatch::query()->where('company_id', $company->getKey())->latest('id')->firstOrFail();
}

function dispatch76Finalize(TestCase $test, Company $company, User $manager, Dispatch $dispatch): void
{
    $test->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('dispatches.finalize', $dispatch->getKey()))
        ->assertRedirect(route('dispatches.show', $dispatch->getKey()));
}

function dispatch76Cancel(TestCase $test, Company $company, User $manager, Dispatch $dispatch): void
{
    $test->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('dispatches.cancel', $dispatch->getKey()))
        ->assertRedirect(route('dispatches.show', $dispatch->getKey()));
}

function dispatch76Capacity(int|string|null $orderLineId): DispatchOrderLineCapacity
{
    return DispatchOrderLineCapacity::query()
        ->where('sales_order_line_id', $orderLineId)
        ->firstOrFail();
}

function dispatch76ActiveGeneration(SalesOrder $order, string $logicalLineKey): SalesOrderReservationGeneration
{
    return SalesOrderReservationGeneration::query()
        ->where('sales_order_id', $order->getKey())
        ->where('logical_line_key', $logicalLineKey)
        ->whereNull('released_at')
        ->firstOrFail();
}
