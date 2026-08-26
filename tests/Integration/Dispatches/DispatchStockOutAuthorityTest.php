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
use App\Modules\Dispatches\Models\Dispatch;
use App\Modules\Dispatches\Stock\DispatchStockOutService;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Enums\StockReservationStatus;
use App\Modules\Inventory\Ledger\PostStockMovementData;
use App\Modules\Inventory\Ledger\StockMovementPoster;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockReservation;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\SalesOrders\Models\SalesOrder;
use App\Modules\SalesOrders\Models\SalesOrderReservationGeneration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('posts an unreserved dispatch exactly once without sales order progress', function (): void {
    [$company, $account, $product, $address, $warehouse, $location, $manager] = dispatch73Fixture('DSP73-PLAIN');
    dispatch73Opening($company, $product, $warehouse, $location, '5');
    $order = dispatch73Order($this, $company, $manager, $account, $product, null, null, '5');
    $dispatch = dispatch73Dispatch($this, $company, $manager, $order, $address, $warehouse, $location, '2');
    $service = app(DispatchStockOutService::class);

    expect(fn () => $service->post($dispatch))->toThrow(LogicException::class);
    $first = DB::transaction(fn (): array => $service->post($dispatch));
    $replay = DB::transaction(fn (): array => $service->post($dispatch));
    $movement = StockMovement::query()->where('movement_type', StockMovementType::DispatchOut->value)->firstOrFail();
    $balance = StockBalance::query()->where('product_id', $product->getKey())->firstOrFail();

    expect($first)->toHaveCount(1)
        ->and($replay)->toHaveCount(1)
        ->and($replay[0]->getKey())->toBe($first[0]->getKey())
        ->and($movement->movement_type)->toBe(StockMovementType::DispatchOut)
        ->and((string) $movement->quantity_delta)->toBe('-2.000000')
        ->and((string) $movement->source_type)->toBe('dispatch_line')
        ->and((string) $movement->effect_type)->toBe('stock.out')
        ->and((string) $balance->refresh()->quantity)->toBe('3.000000')
        ->and(DB::table('sales_order_line_progress_effects')->count())->toBe(0)
        ->and(StockMovement::query()->where('movement_type', StockMovementType::DispatchOut->value)->count())->toBe(1);
});

it('consumes a full reservation before dispatch stock out', function (): void {
    [$company, $account, $product, $address, $warehouse, $location, $manager] = dispatch73Fixture('DSP73-FULL');
    dispatch73Opening($company, $product, $warehouse, $location, '5');
    $order = dispatch73Order($this, $company, $manager, $account, $product, $warehouse, $location, '5');
    $dispatch = dispatch73Dispatch($this, $company, $manager, $order, $address, $warehouse, $location, '5');
    $generation = SalesOrderReservationGeneration::query()->firstOrFail();
    $reservation = StockReservation::query()->findOrFail($generation->stock_reservation_id);

    DB::transaction(fn (): array => app(DispatchStockOutService::class)->post($dispatch));

    $generation->refresh();
    $reservation->refresh();
    $balance = StockBalance::query()->where('product_id', $product->getKey())->firstOrFail();
    expect($reservation->statusEnum())->toBe(StockReservationStatus::Consumed)
        ->and($reservation->consumed_at)->not->toBeNull()
        ->and($generation->released_at)->not->toBeNull()
        ->and(SalesOrderReservationGeneration::query()->whereNull('released_at')->count())->toBe(0)
        ->and((string) $balance->refresh()->quantity)->toBe('0.000000')
        ->and((string) $balance->reserved_quantity)->toBe('0.000000')
        ->and(DB::table('sales_order_line_progress_effects')->count())->toBe(0);
});

it('releases a partial reservation and reserves the exact remainder', function (): void {
    [$company, $account, $product, $address, $warehouse, $location, $manager] = dispatch73Fixture('DSP73-PART');
    dispatch73Opening($company, $product, $warehouse, $location, '10');
    $order = dispatch73Order($this, $company, $manager, $account, $product, $warehouse, $location, '10');
    $dispatch = dispatch73Dispatch($this, $company, $manager, $order, $address, $warehouse, $location, '4');
    $oldGeneration = SalesOrderReservationGeneration::query()->firstOrFail();
    $oldReservation = StockReservation::query()->findOrFail($oldGeneration->stock_reservation_id);

    DB::transaction(fn (): array => app(DispatchStockOutService::class)->post($dispatch));

    $oldGeneration->refresh();
    $oldReservation->refresh();
    $generations = SalesOrderReservationGeneration::query()->orderBy('generation')->get();
    $activeGeneration = $generations->last();
    $activeReservation = StockReservation::query()->findOrFail($activeGeneration->stock_reservation_id);
    $balance = StockBalance::query()->where('product_id', $product->getKey())->firstOrFail();

    expect($generations)->toHaveCount(2)
        ->and($oldReservation->statusEnum())->toBe(StockReservationStatus::Released)
        ->and($oldGeneration->released_at)->not->toBeNull()
        ->and((int) $activeGeneration->generation)->toBe(2)
        ->and((string) $activeGeneration->quantity)->toBe('6.000000')
        ->and($activeReservation->statusEnum())->toBe(StockReservationStatus::Active)
        ->and((string) $activeReservation->quantity)->toBe('6.000000')
        ->and((string) $balance->refresh()->quantity)->toBe('6.000000')
        ->and((string) $balance->reserved_quantity)->toBe('6.000000')
        ->and((string) $balance->available_quantity)->toBe('0.000000');

    DB::transaction(fn (): array => app(DispatchStockOutService::class)->post($dispatch));
    expect(SalesOrderReservationGeneration::query()->count())->toBe(2)
        ->and(StockMovement::query()->where('movement_type', StockMovementType::DispatchOut->value)->count())->toBe(1)
        ->and((string) $balance->refresh()->reserved_quantity)->toBe('6.000000');
});

it('rolls reservation and stock effects back with the owning transaction', function (): void {
    [$company, $account, $product, $address, $warehouse, $location, $manager] = dispatch73Fixture('DSP73-ROLLBACK');
    dispatch73Opening($company, $product, $warehouse, $location, '10');
    $order = dispatch73Order($this, $company, $manager, $account, $product, $warehouse, $location, '10');
    $dispatch = dispatch73Dispatch($this, $company, $manager, $order, $address, $warehouse, $location, '4');
    $generation = SalesOrderReservationGeneration::query()->firstOrFail();
    $reservationId = (int) $generation->stock_reservation_id;

    DB::beginTransaction();
    try {
        app(DispatchStockOutService::class)->post($dispatch);
        expect(StockMovement::query()->where('movement_type', StockMovementType::DispatchOut->value)->count())->toBe(1)
            ->and(SalesOrderReservationGeneration::query()->count())->toBe(2);
    } finally {
        DB::rollBack();
    }

    $reservation = StockReservation::query()->findOrFail($reservationId);
    $balance = StockBalance::query()->where('product_id', $product->getKey())->firstOrFail();
    expect($reservation->statusEnum())->toBe(StockReservationStatus::Active)
        ->and(SalesOrderReservationGeneration::query()->count())->toBe(1)
        ->and(SalesOrderReservationGeneration::query()->firstOrFail()->released_at)->toBeNull()
        ->and(StockMovement::query()->where('movement_type', StockMovementType::DispatchOut->value)->count())->toBe(0)
        ->and((string) $balance->refresh()->quantity)->toBe('10.000000')
        ->and((string) $balance->reserved_quantity)->toBe('10.000000');
});

it('enforces dispatch_out lineage and freezes its source dispatch line', function (): void {
    [$company, $account, $product, $address, $warehouse, $location, $manager] = dispatch73Fixture('DSP73-DB');
    dispatch73Opening($company, $product, $warehouse, $location, '5');
    $order = dispatch73Order($this, $company, $manager, $account, $product, null, null, '5');
    $dispatch = dispatch73Dispatch($this, $company, $manager, $order, $address, $warehouse, $location, '2');
    $line = $dispatch->lines()->firstOrFail();

    expect(fn () => DB::transaction(fn () => app(StockMovementPoster::class)->post(new PostStockMovementData(
        sourceEffect: new SourceEffectIdentity((int) $company->getKey(), 'dispatch_line', (string) $line->getKey(), 'stock.out'),
        productId: (int) $product->getKey(), warehouseId: (int) $warehouse->getKey(), locationId: (int) $location->getKey(),
        movementType: StockMovementType::DispatchOut, quantity: '1',
    ))))->toThrow(QueryException::class);

    DB::transaction(fn (): array => app(DispatchStockOutService::class)->post($dispatch));
    expect(fn () => DB::table('dispatch_lines')->where('id', $line->getKey())->update(['quantity' => '1.000000']))
        ->toThrow(QueryException::class);
    expect(fn () => DB::table('dispatch_lines')->where('id', $line->getKey())->delete())
        ->toThrow(QueryException::class);
});

/** @return array{Company,Account,Product,AccountAddress,Warehouse,WarehouseLocation,User} */
function dispatch73Fixture(string $code): array
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
        'label' => 'Ana Sevk', 'recipient_name' => 'Depo Teslim', 'line1' => 'Mars Cad. 73', 'line2' => null,
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

    return [$company, $account, $product, $address, $warehouse, $location, dispatch73Actor($company, $code)];
}

function dispatch73Actor(Company $company, string $suffix): User
{
    $user = User::query()->create([
        'name' => 'M73 '.$suffix, 'email' => strtolower($suffix).'@m73.test',
        'password' => 'correct-password', 'status' => UserStatus::Active,
    ]);
    $membership = CompanyMembership::query()->create([
        'company_id' => $company->getKey(), 'user_id' => $user->getKey(), 'is_active' => true, 'joined_at' => now(),
    ]);
    $role = Role::query()->create(['company_id' => $company->getKey(), 'code' => 'm73', 'name' => 'M73', 'is_active' => true]);
    foreach ([PermissionKey::SalesOrderView, PermissionKey::SalesOrderManage, PermissionKey::DispatchView, PermissionKey::DispatchManage] as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}

function dispatch73Opening(Company $company, Product $product, Warehouse $warehouse, WarehouseLocation $location, string $quantity): void
{
    DB::transaction(fn () => app(StockMovementPoster::class)->post(new PostStockMovementData(
        sourceEffect: new SourceEffectIdentity((int) $company->getKey(), 'dispatch.test', 'opening-'.$company->code, 'inventory.opening'),
        productId: (int) $product->getKey(), warehouseId: (int) $warehouse->getKey(), locationId: (int) $location->getKey(),
        movementType: StockMovementType::OpeningIn, quantity: $quantity, unitCost: '10',
    )));
}

function dispatch73Order(TestCase $test, Company $company, User $manager, Account $account, Product $product, ?Warehouse $warehouse, ?WarehouseLocation $location, string $quantity): SalesOrder
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

function dispatch73Dispatch(TestCase $test, Company $company, User $manager, SalesOrder $order, AccountAddress $address, Warehouse $warehouse, WarehouseLocation $location, string $quantity): Dispatch
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
