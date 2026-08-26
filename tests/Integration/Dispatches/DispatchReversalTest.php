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
use App\Modules\Dispatches\Actions\CancelDispatch;
use App\Modules\Dispatches\Enums\DispatchStatus;
use App\Modules\Dispatches\Models\Dispatch;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Enums\StockReservationStatus;
use App\Modules\Inventory\Ledger\PostStockMovementData;
use App\Modules\Inventory\Ledger\StockMovementPoster;
use App\Modules\Inventory\Ledger\StockMovementReverser;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\SalesOrders\Enums\SalesOrderProgressType;
use App\Modules\SalesOrders\Models\SalesOrder;
use App\Modules\SalesOrders\Models\SalesOrderLineProgress;
use App\Modules\SalesOrders\Models\SalesOrderLineProgressEffect;
use App\Modules\SalesOrders\Models\SalesOrderReservationGeneration;
use App\Modules\SalesOrders\Progress\SalesOrderProgressService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('reverses an unreserved finalized dispatch exactly once and reopens order progress', function (): void {
    [$company, $account, $product, $address, $warehouse, $location, $manager] = dispatch75Fixture('DSP75-REPLAY');
    dispatch75Opening($company, $product, $warehouse, $location, '5');
    $order = dispatch75Order($this, $company, $manager, $account, $product, null, null, '5');
    $dispatch = dispatch75Dispatch($this, $company, $manager, $order, $address, $warehouse, $location, '2');
    $line = $dispatch->lines()->firstOrFail();

    dispatch75Finalize($this, $company, $manager, $dispatch);

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('dispatches.cancel', $dispatch->getKey()))
        ->assertRedirect(route('dispatches.show', $dispatch->getKey()));

    $dispatch->refresh();
    $balance = StockBalance::query()->where('product_id', $product->getKey())->firstOrFail();
    $projection = SalesOrderLineProgress::query()
        ->where('sales_order_line_id', $line->sales_order_line_id)
        ->firstOrFail();
    $stockOriginal = StockMovement::query()
        ->where('source_type', 'dispatch_line')
        ->where('source_id', (string) $line->getKey())
        ->where('effect_type', 'stock.out')
        ->firstOrFail();
    $stockReversal = StockMovement::query()
        ->where('source_type', 'dispatch_line')
        ->where('source_id', (string) $line->getKey())
        ->where('effect_type', 'stock.out.reverse')
        ->firstOrFail();
    $progressOriginal = SalesOrderLineProgressEffect::query()
        ->where('source_type', 'dispatch_line')
        ->where('source_id', (string) $line->getKey())
        ->where('effect_type', 'progress.dispatch')
        ->firstOrFail();
    $progressReversal = SalesOrderLineProgressEffect::query()
        ->where('source_type', 'dispatch_line')
        ->where('source_id', (string) $line->getKey())
        ->where('effect_type', 'progress.dispatch.reverse')
        ->firstOrFail();

    expect($dispatch->statusEnum())->toBe(DispatchStatus::Cancelled)
        ->and($dispatch->cancelled_at)->not->toBeNull()
        ->and((string) $balance->quantity)->toBe('5.000000')
        ->and((string) $projection->net_dispatched_quantity)->toBe('0.000000')
        ->and((string) $projection->dispatch_remaining_quantity)->toBe('5.000000')
        ->and($stockReversal->movement_type)->toBe(StockMovementType::AdjustmentIn)
        ->and((int) $stockReversal->reversal_of_movement_id)->toBe((int) $stockOriginal->getKey())
        ->and((string) $stockReversal->quantity_delta)->toBe('2.000000')
        ->and((int) $progressReversal->reversal_of_progress_effect_id)->toBe((int) $progressOriginal->getKey())
        ->and((string) $progressReversal->quantity_delta)->toBe('-2.000000');

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('dispatches.cancel', $dispatch->getKey()))
        ->assertRedirect(route('dispatches.show', $dispatch->getKey()));

    expect(StockMovement::query()->where('source_type', 'dispatch_line')->where('source_id', (string) $line->getKey())->count())->toBe(2)
        ->and(SalesOrderLineProgressEffect::query()->where('source_type', 'dispatch_line')->where('source_id', (string) $line->getKey())->count())->toBe(2);
});

it('rebuilds the exact active reservation generation after partial shipment reversal', function (): void {
    [$company, $account, $product, $address, $warehouse, $location, $manager] = dispatch75Fixture('DSP75-RESERVE');
    dispatch75Opening($company, $product, $warehouse, $location, '5');
    $order = dispatch75Order($this, $company, $manager, $account, $product, $warehouse, $location, '5');
    $dispatch = dispatch75Dispatch($this, $company, $manager, $order, $address, $warehouse, $location, '2');
    $orderLine = $order->lines()->firstOrFail();

    dispatch75Finalize($this, $company, $manager, $dispatch);

    $activeAfterShip = SalesOrderReservationGeneration::query()
        ->where('sales_order_id', $order->getKey())
        ->where('logical_line_key', $orderLine->logical_line_key)
        ->whereNull('released_at')
        ->firstOrFail();
    expect((string) $activeAfterShip->quantity)->toBe('3.000000');

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('dispatches.cancel', $dispatch->getKey()))
        ->assertRedirect();

    $active = SalesOrderReservationGeneration::query()
        ->where('sales_order_id', $order->getKey())
        ->where('logical_line_key', $orderLine->logical_line_key)
        ->whereNull('released_at')
        ->firstOrFail();
    $reservation = $active->stockReservation()->firstOrFail();
    $balance = StockBalance::query()->where('product_id', $product->getKey())->firstOrFail();

    expect($dispatch->refresh()->statusEnum())->toBe(DispatchStatus::Cancelled)
        ->and((string) $active->quantity)->toBe('5.000000')
        ->and((int) $active->generation)->toBe(3)
        ->and($reservation->statusEnum())->toBe(StockReservationStatus::Active)
        ->and((string) $reservation->quantity)->toBe('5.000000')
        ->and((string) $balance->quantity)->toBe('5.000000')
        ->and((string) $balance->reserved_quantity)->toBe('5.000000')
        ->and(SalesOrderReservationGeneration::query()->where('sales_order_id', $order->getKey())->count())->toBe(3);
});

it('rolls every reversal effect back when cancelled status cannot commit', function (): void {
    [$company, $account, $product, $address, $warehouse, $location, $manager] = dispatch75Fixture('DSP75-ROLLBACK');
    dispatch75Opening($company, $product, $warehouse, $location, '5');
    $order = dispatch75Order($this, $company, $manager, $account, $product, null, null, '5');
    $dispatch = dispatch75Dispatch($this, $company, $manager, $order, $address, $warehouse, $location, '2');
    dispatch75Finalize($this, $company, $manager, $dispatch);

    DB::statement("ALTER TABLE dispatches ADD CONSTRAINT dispatch75_force_cancel_failure CHECK (status <> 'cancelled')");
    $this->actingAs($manager);
    session(['active_company_id' => $company->getKey()]);
    app(ActiveCompanyContext::class)->set($company);

    try {
        expect(fn () => app(CancelDispatch::class)->handle((int) $dispatch->getKey()))
            ->toThrow(QueryException::class);
    } finally {
        DB::statement('ALTER TABLE dispatches DROP CONSTRAINT IF EXISTS dispatch75_force_cancel_failure');
    }

    $balance = StockBalance::query()->where('product_id', $product->getKey())->firstOrFail();
    $projection = SalesOrderLineProgress::query()->where('sales_order_id', $order->getKey())->firstOrFail();

    expect($dispatch->refresh()->statusEnum())->toBe(DispatchStatus::Finalized)
        ->and($dispatch->cancelled_at)->toBeNull()
        ->and((string) $balance->quantity)->toBe('3.000000')
        ->and((string) $projection->net_dispatched_quantity)->toBe('2.000000')
        ->and(StockMovement::query()->where('effect_type', 'stock.out.reverse')->count())->toBe(0)
        ->and(SalesOrderLineProgressEffect::query()->where('effect_type', 'progress.dispatch.reverse')->count())->toBe(0);
});

it('blocks incomplete raw cancellation and stranded source-specific reversals', function (): void {
    [$company, $account, $product, $address, $warehouse, $location, $manager] = dispatch75Fixture('DSP75-DB');
    dispatch75Opening($company, $product, $warehouse, $location, '5');
    $order = dispatch75Order($this, $company, $manager, $account, $product, null, null, '5');
    $dispatch = dispatch75Dispatch($this, $company, $manager, $order, $address, $warehouse, $location, '2');
    $line = $dispatch->lines()->firstOrFail();
    dispatch75Finalize($this, $company, $manager, $dispatch);

    expect(fn () => DB::table('dispatches')->where('id', $dispatch->getKey())->update([
        'status' => 'cancelled',
        'cancelled_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    $progressOriginal = SalesOrderLineProgressEffect::query()
        ->where('source_type', 'dispatch_line')
        ->where('source_id', (string) $line->getKey())
        ->where('effect_type', 'progress.dispatch')
        ->firstOrFail();
    expect(fn () => DB::transaction(fn () => app(SalesOrderProgressService::class)->reverse(
        new SourceEffectIdentity((int) $company->getKey(), 'dispatch_line', (string) $line->getKey(), 'progress.dispatch.reverse'),
        (int) $progressOriginal->getKey(),
    )))->toThrow(QueryException::class);

    $stockOriginal = StockMovement::query()
        ->where('source_type', 'dispatch_line')
        ->where('source_id', (string) $line->getKey())
        ->where('effect_type', 'stock.out')
        ->firstOrFail();
    expect(fn () => DB::transaction(fn () => app(StockMovementReverser::class)->reverse(
        (int) $stockOriginal->getKey(),
        new SourceEffectIdentity((int) $company->getKey(), 'dispatch_line', (string) $line->getKey(), 'stock.out.reverse'),
    )))->toThrow(QueryException::class);

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('dispatches.cancel', $dispatch->getKey()))
        ->assertRedirect();

    expect(fn () => DB::table('dispatches')->where('id', $dispatch->getKey())->update([
        'note' => 'mutated',
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
    expect(fn () => DB::table('dispatches')->where('id', $dispatch->getKey())->delete())
        ->toThrow(QueryException::class);
});

/** @return array{Company,Account,Product,AccountAddress,Warehouse,WarehouseLocation,User} */
function dispatch75Fixture(string $code): array
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
        'company_id' => $company->getKey(), 'code' => 'SKU-'.$code, 'status' => ProductStatus::Active, 'name' => 'Ürün '.$code,
        'category_id' => $category->getKey(), 'unit_id' => $unit->getKey(), 'tax_id' => $tax->getKey(),
        'sale_price_net' => '100.000000', 'purchase_price_net' => '60.000000',
    ]);
    $address = AccountAddress::query()->create([
        'company_id' => $company->getKey(), 'account_id' => $account->getKey(), 'type' => AccountAddressType::Shipping,
        'label' => 'Ana Sevk', 'recipient_name' => 'Depo Teslim', 'line1' => 'Mars Cad. 75', 'line2' => null,
        'district' => 'Şişli', 'city' => 'İstanbul', 'postal_code' => '34360', 'country_code' => 'TR', 'is_default' => true,
    ]);
    $warehouse = Warehouse::query()->create(['company_id' => $company->getKey(), 'code' => 'MAIN', 'name' => 'Merkez Depo', 'is_active' => true]);
    $location = WarehouseLocation::query()->create([
        'company_id' => $company->getKey(), 'warehouse_id' => $warehouse->getKey(), 'code' => 'A-01', 'name' => 'A Rafı', 'is_active' => true,
    ]);
    DocumentSequence::query()->create([
        'company_id' => $company->getKey(), 'document_type' => DocumentType::SalesOrder, 'series_code' => 'default',
        'prefix' => 'SO-', 'padding' => 4, 'next_value' => 1, 'is_active' => true,
    ]);
    DocumentSequence::query()->create([
        'company_id' => $company->getKey(), 'document_type' => DocumentType::Dispatch, 'series_code' => 'default',
        'prefix' => 'DSP-', 'padding' => 4, 'next_value' => 1, 'is_active' => true,
    ]);

    return [$company, $account, $product, $address, $warehouse, $location, dispatch75Actor($company, $code)];
}

function dispatch75Actor(Company $company, string $suffix): User
{
    $user = User::query()->create([
        'name' => 'M75 '.$suffix, 'email' => strtolower($suffix).'@m75.test',
        'password' => 'correct-password', 'status' => UserStatus::Active,
    ]);
    $membership = CompanyMembership::query()->create([
        'company_id' => $company->getKey(), 'user_id' => $user->getKey(), 'is_active' => true, 'joined_at' => now(),
    ]);
    $role = Role::query()->create(['company_id' => $company->getKey(), 'code' => 'm75', 'name' => 'M75', 'is_active' => true]);
    foreach ([PermissionKey::SalesOrderView, PermissionKey::SalesOrderManage, PermissionKey::DispatchView, PermissionKey::DispatchManage] as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}

function dispatch75Opening(Company $company, Product $product, Warehouse $warehouse, WarehouseLocation $location, string $quantity): void
{
    DB::transaction(fn () => app(StockMovementPoster::class)->post(new PostStockMovementData(
        sourceEffect: new SourceEffectIdentity((int) $company->getKey(), 'dispatch.test', 'opening-'.$company->code, 'inventory.opening'),
        productId: (int) $product->getKey(), warehouseId: (int) $warehouse->getKey(), locationId: (int) $location->getKey(),
        movementType: StockMovementType::OpeningIn, quantity: $quantity, unitCost: '10',
    )));
}

function dispatch75Order(TestCase $test, Company $company, User $manager, Account $account, Product $product, ?Warehouse $warehouse, ?WarehouseLocation $location, string $quantity): SalesOrder
{
    $test->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])->post('/sales-orders', [
        'series_code' => 'default', 'account_id' => $account->getKey(), 'order_date' => '2026-08-26',
        'currency_code' => 'TRY', 'document_discount_rate' => '0', 'note' => null,
        'lines' => [[
            'logical_line_key' => null, 'product_id' => $product->getKey(),
            'warehouse_id' => $warehouse?->getKey(), 'location_id' => $location?->getKey(),
            'description' => null, 'quantity' => $quantity, 'unit_price' => '100', 'price_basis' => 'net',
            'line_discount_rate' => '0', 'tax_zero_reason_id' => null,
        ]],
    ])->assertRedirect();

    return SalesOrder::query()->where('company_id', $company->getKey())->latest('id')->firstOrFail();
}

function dispatch75Dispatch(TestCase $test, Company $company, User $manager, SalesOrder $order, AccountAddress $address, Warehouse $warehouse, WarehouseLocation $location, string $quantity): Dispatch
{
    $line = $order->lines()->firstOrFail();
    $test->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])->post('/dispatches', [
        'series_code' => 'default', 'sales_order_id' => $order->getKey(), 'source_address_id' => $address->getKey(),
        'dispatch_date' => '2026-08-26', 'lines' => [[
            'sales_order_line_id' => $line->getKey(), 'quantity' => $quantity,
            'allocation_key' => $warehouse->getKey().':'.$location->getKey(),
        ]],
    ])->assertRedirect();

    return Dispatch::query()->where('company_id', $company->getKey())->latest('id')->firstOrFail();
}

function dispatch75Finalize(TestCase $test, Company $company, User $manager, Dispatch $dispatch): void
{
    $test->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('dispatches.finalize', $dispatch->getKey()))
        ->assertRedirect(route('dispatches.show', $dispatch->getKey()));
}
