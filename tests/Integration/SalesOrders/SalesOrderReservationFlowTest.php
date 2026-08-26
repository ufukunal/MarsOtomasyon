<?php

use App\Foundation\Identity\SourceEffectIdentity;
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
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Enums\StockReservationStatus;
use App\Modules\Inventory\Ledger\PostStockMovementData;
use App\Modules\Inventory\Ledger\StockMovementPoster;
use App\Modules\Inventory\Models\StockBalance;
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

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('binds a manual order line to a stable reservation generation and reserves available stock', function (): void {
    [$company, $account, $product, $warehouse, $location] = m63Fixture('M63-CREATE');
    $manager = m63Actor($company, 'create');
    m63Opening($company, $product, $warehouse, $location, '10');

    $response = $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->post('/sales-orders', m63Payload($account, $product, $warehouse, $location, '4'));

    $order = SalesOrder::query()->where('company_id', $company->getKey())->firstOrFail();
    $line = $order->lines()->firstOrFail();
    $generation = SalesOrderReservationGeneration::query()->firstOrFail();
    $reservation = StockReservation::query()->firstOrFail();
    $balance = StockBalance::query()->where('product_id', $product->getKey())->firstOrFail();

    $response->assertRedirect('/sales-orders/'.$order->getKey());
    expect((string) $line->logical_line_key)->toMatch('/^[0-9a-f-]{36}$/')
        ->and((int) $line->warehouse_id)->toBe((int) $warehouse->getKey())
        ->and((int) $line->location_id)->toBe((int) $location->getKey())
        ->and((string) $generation->logical_line_key)->toBe((string) $line->logical_line_key)
        ->and((int) $generation->generation)->toBe(1)
        ->and((int) $generation->stock_reservation_id)->toBe((int) $reservation->getKey())
        ->and($reservation->statusEnum())->toBe(StockReservationStatus::Active)
        ->and((string) $reservation->quantity)->toBe('4.000000')
        ->and((string) $balance->refresh()->reserved_quantity)->toBe('4.000000')
        ->and((string) $balance->available_quantity)->toBe('6.000000');
});

it('keeps logical identity across delete-recreate edits and advances immutable reservation generations', function (): void {
    [$company, $account, $product, $warehouse, $location] = m63Fixture('M63-GEN');
    $manager = m63Actor($company, 'generation');
    m63Opening($company, $product, $warehouse, $location, '10');

    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->post('/sales-orders', m63Payload($account, $product, $warehouse, $location, '4'))->assertRedirect();
    $order = SalesOrder::query()->firstOrFail();
    $firstLine = $order->lines()->firstOrFail();
    $firstLineId = (int) $firstLine->getKey();
    $logicalKey = (string) $firstLine->logical_line_key;
    $firstGeneration = SalesOrderReservationGeneration::query()->firstOrFail();

    $payload = m63Payload($account, $product, $warehouse, $location, '6', $logicalKey);
    unset($payload['series_code']);
    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->put('/sales-orders/'.$order->getKey(), $payload)->assertRedirect('/sales-orders/'.$order->getKey());

    $newLine = $order->fresh()->lines()->firstOrFail();
    $generations = SalesOrderReservationGeneration::query()->orderBy('generation')->get();
    $balance = StockBalance::query()->where('product_id', $product->getKey())->firstOrFail();

    expect((int) $newLine->getKey())->not->toBe($firstLineId)
        ->and((string) $newLine->logical_line_key)->toBe($logicalKey)
        ->and($generations)->toHaveCount(2)
        ->and((int) $generations[0]->generation)->toBe(1)
        ->and($generations[0]->released_at)->not->toBeNull()
        ->and((int) $generations[1]->generation)->toBe(2)
        ->and($generations[1]->released_at)->toBeNull()
        ->and((string) $generations[1]->quantity)->toBe('6.000000')
        ->and((string) $balance->refresh()->reserved_quantity)->toBe('6.000000')
        ->and((string) $balance->available_quantity)->toBe('4.000000');

    $firstGeneration->refresh();
    expect(fn () => DB::table('sales_order_reservation_generations')->where('id', $firstGeneration->getKey())->update(['quantity' => '1.000000']))
        ->toThrow(QueryException::class);
    expect(fn () => DB::table('sales_order_reservation_generations')->where('id', $firstGeneration->getKey())->delete())
        ->toThrow(QueryException::class);
});

it('does not mint a new generation for an identical allocation replay', function (): void {
    [$company, $account, $product, $warehouse, $location] = m63Fixture('M63-NOOP');
    $manager = m63Actor($company, 'noop');
    m63Opening($company, $product, $warehouse, $location, '10');

    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->post('/sales-orders', m63Payload($account, $product, $warehouse, $location, '3'))->assertRedirect();
    $order = SalesOrder::query()->firstOrFail();
    $logicalKey = (string) $order->lines()->firstOrFail()->logical_line_key;

    $payload = m63Payload($account, $product, $warehouse, $location, '3', $logicalKey);
    unset($payload['series_code']);
    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->put('/sales-orders/'.$order->getKey(), $payload)->assertRedirect();

    expect(SalesOrderReservationGeneration::query()->count())->toBe(1)
        ->and(StockReservation::query()->count())->toBe(1)
        ->and((string) StockBalance::query()->where('product_id', $product->getKey())->firstOrFail()->reserved_quantity)->toBe('3.000000');
});

it('rolls back line recreation and reservation release when a quantity change exceeds physical availability', function (): void {
    [$company, $account, $product, $warehouse, $location] = m63Fixture('M63-ROLLBACK');
    $manager = m63Actor($company, 'rollback');
    m63Opening($company, $product, $warehouse, $location, '5');

    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->post('/sales-orders', m63Payload($account, $product, $warehouse, $location, '4'))->assertRedirect();
    $order = SalesOrder::query()->firstOrFail();
    $line = $order->lines()->firstOrFail();
    $lineId = (int) $line->getKey();
    $logicalKey = (string) $line->logical_line_key;

    $payload = m63Payload($account, $product, $warehouse, $location, '6', $logicalKey);
    unset($payload['series_code']);
    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->from('/sales-orders/'.$order->getKey().'/edit')
        ->put('/sales-orders/'.$order->getKey(), $payload)
        ->assertRedirect('/sales-orders/'.$order->getKey().'/edit')
        ->assertSessionHasErrors();

    $persistedLine = $order->fresh()->lines()->firstOrFail();
    $generation = SalesOrderReservationGeneration::query()->firstOrFail();
    $reservation = StockReservation::query()->firstOrFail();
    $balance = StockBalance::query()->where('product_id', $product->getKey())->firstOrFail();

    expect((int) $persistedLine->getKey())->toBe($lineId)
        ->and((string) $persistedLine->quantity)->toBe('4.000000')
        ->and(SalesOrderReservationGeneration::query()->count())->toBe(1)
        ->and($generation->released_at)->toBeNull()
        ->and($reservation->statusEnum())->toBe(StockReservationStatus::Active)
        ->and((string) $balance->refresh()->reserved_quantity)->toBe('4.000000')
        ->and((string) $balance->available_quantity)->toBe('1.000000');
});

it('rejects foreign allocation ownership before mutating the order', function (): void {
    [$companyA, $accountA, $productA, $warehouseA, $locationA] = m63Fixture('M63-TENANT-A');
    [$companyB, , , $warehouseB, $locationB] = m63Fixture('M63-TENANT-B');
    $manager = m63Actor($companyA, 'tenant');
    m63Opening($companyA, $productA, $warehouseA, $locationA, '5');

    $response = $this->actingAs($manager)->withSession(['active_company_id' => $companyA->getKey()])
        ->from('/sales-orders/create')
        ->post('/sales-orders', m63Payload($accountA, $productA, $warehouseB, $locationB, '1'));

    $response->assertRedirect('/sales-orders/create')->assertSessionHasErrors('lines.0.warehouse_id');
    expect(SalesOrder::query()->where('company_id', $companyA->getKey())->count())->toBe(0)
        ->and(SalesOrderReservationGeneration::query()->where('company_id', $companyA->getKey())->count())->toBe(0)
        ->and((int) $companyB->getKey())->not->toBe((int) $companyA->getKey());
});

/** @return array{Company, Account, Product, Warehouse, WarehouseLocation} */
function m63Fixture(string $code): array
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
        'company_id' => $company->getKey(), 'code' => 'SKU-'.$code, 'status' => ProductStatus::Active,
        'name' => 'Ürün '.$code, 'category_id' => $category->getKey(), 'unit_id' => $unit->getKey(), 'tax_id' => $tax->getKey(),
        'sale_price_net' => '100.000000', 'purchase_price_net' => '60.000000',
    ]);
    $warehouse = Warehouse::query()->create(['company_id' => $company->getKey(), 'code' => 'MAIN', 'name' => 'Merkez Depo', 'is_active' => true]);
    $location = WarehouseLocation::query()->create([
        'company_id' => $company->getKey(), 'warehouse_id' => $warehouse->getKey(), 'code' => 'A-01', 'name' => 'A Rafı', 'is_active' => true,
    ]);
    DocumentSequence::query()->create([
        'company_id' => $company->getKey(), 'document_type' => DocumentType::SalesOrder, 'series_code' => 'default',
        'prefix' => 'SO-', 'padding' => 4, 'next_value' => 1, 'is_active' => true,
    ]);

    return [$company, $account, $product, $warehouse, $location];
}

function m63Actor(Company $company, string $suffix): User
{
    $user = User::query()->create([
        'name' => 'M63 '.$suffix, 'email' => strtolower((string) $company->code).'-'.$suffix.'@m63.test',
        'password' => 'correct-password', 'status' => UserStatus::Active,
    ]);
    $membership = CompanyMembership::query()->create([
        'company_id' => $company->getKey(), 'user_id' => $user->getKey(), 'is_active' => true, 'joined_at' => now(),
    ]);
    $role = Role::query()->create([
        'company_id' => $company->getKey(), 'code' => 'm63-'.$suffix, 'name' => 'M63 '.$suffix, 'is_active' => true,
    ]);
    app(GrantPermissionToRole::class)->handle($role, PermissionKey::SalesOrderView);
    app(GrantPermissionToRole::class)->handle($role, PermissionKey::SalesOrderManage);
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}

function m63Opening(Company $company, Product $product, Warehouse $warehouse, WarehouseLocation $location, string $quantity): void
{
    DB::transaction(fn () => app(StockMovementPoster::class)->post(new PostStockMovementData(
        sourceEffect: new SourceEffectIdentity((int) $company->getKey(), 'sales_order.test', 'opening-'.$company->code, 'inventory.opening'),
        productId: (int) $product->getKey(), warehouseId: (int) $warehouse->getKey(), locationId: (int) $location->getKey(),
        movementType: StockMovementType::OpeningIn, quantity: $quantity, unitCost: '10',
    )));
}

/** @return array<string, mixed> */
function m63Payload(Account $account, Product $product, Warehouse $warehouse, WarehouseLocation $location, string $quantity, ?string $logicalLineKey = null): array
{
    return [
        'series_code' => 'default', 'account_id' => $account->getKey(), 'order_date' => '2026-08-26',
        'currency_code' => 'TRY', 'document_discount_rate' => '0', 'note' => null,
        'lines' => [[
            'logical_line_key' => $logicalLineKey, 'product_id' => $product->getKey(),
            'warehouse_id' => $warehouse->getKey(), 'location_id' => $location->getKey(),
            'description' => null, 'quantity' => $quantity, 'unit_price' => '100', 'price_basis' => 'net',
            'line_discount_rate' => '0', 'tax_zero_reason_id' => null,
        ]],
    ];
}
