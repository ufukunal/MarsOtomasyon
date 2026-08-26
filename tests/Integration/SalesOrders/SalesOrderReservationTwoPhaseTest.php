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
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('releases obsolete logical allocations before reserving their replacement capacity', function (): void {
    [$company, $account, $product, $warehouse, $location] = m63PhaseFixture();
    $manager = m63PhaseActor($company);
    m63PhaseOpening($company, $product, $warehouse, $location, '5');

    $originalKey = (string) Str::uuid();
    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/sales-orders', m63PhasePayload($account, $product, $warehouse, $location, $originalKey))
        ->assertRedirect();

    $order = SalesOrder::query()->where('company_id', $company->getKey())->firstOrFail();
    $replacementKey = (string) Str::uuid();
    $payload = m63PhasePayload($account, $product, $warehouse, $location, $replacementKey);
    unset($payload['series_code']);

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->put('/sales-orders/'.$order->getKey(), $payload)
        ->assertRedirect('/sales-orders/'.$order->getKey());

    $line = $order->fresh()->lines()->firstOrFail();
    $generations = SalesOrderReservationGeneration::query()
        ->where('sales_order_id', $order->getKey())
        ->orderBy('id')
        ->get();
    $reservations = StockReservation::query()->orderBy('id')->get();
    $balance = StockBalance::query()->where('product_id', $product->getKey())->firstOrFail();

    expect((string) $line->logical_line_key)->toBe($replacementKey)
        ->and($generations)->toHaveCount(2)
        ->and((string) $generations[0]->logical_line_key)->toBe($originalKey)
        ->and($generations[0]->released_at)->not->toBeNull()
        ->and((string) $generations[1]->logical_line_key)->toBe($replacementKey)
        ->and($generations[1]->released_at)->toBeNull()
        ->and($reservations)->toHaveCount(2)
        ->and($reservations[0]->statusEnum())->toBe(StockReservationStatus::Released)
        ->and($reservations[1]->statusEnum())->toBe(StockReservationStatus::Active)
        ->and((string) $balance->refresh()->reserved_quantity)->toBe('5.000000')
        ->and((string) $balance->available_quantity)->toBe('0.000000');
});

/** @return array{Company, Account, Product, Warehouse, WarehouseLocation} */
function m63PhaseFixture(): array
{
    $company = Company::query()->create(['code' => 'M63-PHASE', 'name' => 'M63 Phase Company']);
    $account = Account::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'CUST',
        'type' => AccountType::Customer,
        'status' => AccountStatus::Active,
        'legal_name' => 'M63 Phase Customer',
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
        'company_id' => $company->getKey(),
        'code' => 'CAT',
        'name' => 'Kategori',
        'is_active' => true,
    ]);
    $unit = Unit::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'ADET',
        'name' => 'Adet',
        'is_active' => true,
    ]);
    $tax = Tax::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'KDV20',
        'name' => 'KDV %20',
        'rate' => '20.000000',
        'is_active' => true,
    ]);
    $product = Product::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'SKU-PHASE',
        'status' => ProductStatus::Active,
        'name' => 'M63 Phase Product',
        'category_id' => $category->getKey(),
        'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(),
        'sale_price_net' => '100.000000',
        'purchase_price_net' => '60.000000',
    ]);
    $warehouse = Warehouse::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'MAIN',
        'name' => 'Merkez Depo',
        'is_active' => true,
    ]);
    $location = WarehouseLocation::query()->create([
        'company_id' => $company->getKey(),
        'warehouse_id' => $warehouse->getKey(),
        'code' => 'A-01',
        'name' => 'A Rafı',
        'is_active' => true,
    ]);
    DocumentSequence::query()->create([
        'company_id' => $company->getKey(),
        'document_type' => DocumentType::SalesOrder,
        'series_code' => 'default',
        'prefix' => 'SO-',
        'padding' => 4,
        'next_value' => 1,
        'is_active' => true,
    ]);

    return [$company, $account, $product, $warehouse, $location];
}

function m63PhaseActor(Company $company): User
{
    $user = User::query()->create([
        'name' => 'M63 Phase Manager',
        'email' => 'm63-phase@example.test',
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
        'code' => 'm63-phase',
        'name' => 'M63 Phase Manager',
        'is_active' => true,
    ]);
    app(GrantPermissionToRole::class)->handle($role, PermissionKey::SalesOrderView);
    app(GrantPermissionToRole::class)->handle($role, PermissionKey::SalesOrderManage);
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}

function m63PhaseOpening(
    Company $company,
    Product $product,
    Warehouse $warehouse,
    WarehouseLocation $location,
    string $quantity,
): void
{
    DB::transaction(fn () => app(StockMovementPoster::class)->post(new PostStockMovementData(
        sourceEffect: new SourceEffectIdentity(
            (int) $company->getKey(),
            'sales_order.test',
            'opening-phase',
            'inventory.opening',
        ),
        productId: (int) $product->getKey(),
        warehouseId: (int) $warehouse->getKey(),
        locationId: (int) $location->getKey(),
        movementType: StockMovementType::OpeningIn,
        quantity: $quantity,
        unitCost: '10',
    )));
}

/** @return array<string, mixed> */
function m63PhasePayload(
    Account $account,
    Product $product,
    Warehouse $warehouse,
    WarehouseLocation $location,
    string $logicalLineKey,
): array
{
    return [
        'series_code' => 'default',
        'account_id' => $account->getKey(),
        'order_date' => '2026-08-26',
        'currency_code' => 'TRY',
        'document_discount_rate' => '0',
        'note' => null,
        'lines' => [[
            'logical_line_key' => $logicalLineKey,
            'product_id' => $product->getKey(),
            'warehouse_id' => $warehouse->getKey(),
            'location_id' => $location->getKey(),
            'description' => null,
            'quantity' => '5',
            'unit_price' => '100',
            'price_basis' => 'net',
            'line_discount_rate' => '0',
            'tax_zero_reason_id' => null,
        ]],
    ];
}
