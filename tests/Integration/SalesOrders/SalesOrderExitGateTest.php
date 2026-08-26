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
use App\Modules\Core\Models\TaxZeroReason;
use App\Modules\Core\Models\User;
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
use App\Modules\SalesOrders\Enums\SalesOrderProgressType;
use App\Modules\SalesOrders\Models\SalesOrder;
use App\Modules\SalesOrders\Models\SalesOrderLineProgressEffect;
use App\Modules\SalesOrders\Models\SalesOrderReservationGeneration;
use App\Modules\SalesOrders\Progress\SalesOrderProgressService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('holds reservation quantity tax and reversal invariants together without creating physical stock effects', function (): void {
    [$company, $account, $product, $reason, $warehouse, $location, $manager] = m67Fixture();
    m67Opening($company, $product, $warehouse, $location, '10');

    $createPayload = m67Payload($account, $product, $reason, $warehouse, $location, '4');
    $createPayload['net_total'] = '0.000001';
    $createPayload['tax_total'] = '999.000000';
    $createPayload['gross_total'] = '999.000001';
    $createPayload['lines'][0]['gross_total'] = '999.000001';

    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->post('/sales-orders', $createPayload)->assertRedirect();

    $order = SalesOrder::query()->where('company_id', $company->getKey())->firstOrFail();
    $firstLine = $order->lines()->firstOrFail();
    $logicalLineKey = (string) $firstLine->logical_line_key;
    $firstGeneration = SalesOrderReservationGeneration::query()->where('sales_order_id', $order->getKey())->firstOrFail();
    $balance = StockBalance::query()->where('product_id', $product->getKey())->firstOrFail();

    expect((string) $order->net_total)->toBe('400.000000')
        ->and((string) $order->tax_total)->toBe('0.000000')
        ->and((string) $order->gross_total)->toBe('400.000000')
        ->and($firstLine->tax_is_zeroed)->toBeTrue()
        ->and((string) $firstLine->tax_rate)->toBe('0.000000')
        ->and((string) $firstLine->tax_zero_reason_code)->toBe('ISTISNA')
        ->and((int) $firstGeneration->generation)->toBe(1)
        ->and($firstGeneration->released_at)->toBeNull()
        ->and((string) $balance->quantity)->toBe('10.000000')
        ->and((string) $balance->reserved_quantity)->toBe('4.000000')
        ->and((string) $balance->available_quantity)->toBe('6.000000')
        ->and(StockMovement::query()->where('company_id', $company->getKey())->count())->toBe(1);

    $updatePayload = m67Payload($account, $product, $reason, $warehouse, $location, '6', $logicalLineKey);
    unset($updatePayload['series_code']);
    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->put('/sales-orders/'.$order->getKey(), $updatePayload)
        ->assertRedirect('/sales-orders/'.$order->getKey());

    $order = $order->fresh();
    $line = $order->lines()->firstOrFail();
    $generations = SalesOrderReservationGeneration::query()
        ->where('sales_order_id', $order->getKey())
        ->orderBy('generation')
        ->get();
    $activeGeneration = $generations->last();
    $activeReservation = StockReservation::query()->findOrFail($activeGeneration->stock_reservation_id);
    $balance->refresh();

    expect((string) $line->logical_line_key)->toBe($logicalLineKey)
        ->and((string) $line->quantity)->toBe('6.000000')
        ->and($line->tax_is_zeroed)->toBeTrue()
        ->and($generations)->toHaveCount(2)
        ->and($generations[0]->released_at)->not->toBeNull()
        ->and((int) $activeGeneration->generation)->toBe(2)
        ->and($activeGeneration->released_at)->toBeNull()
        ->and($activeReservation->statusEnum())->toBe(StockReservationStatus::Active)
        ->and((string) $activeReservation->quantity)->toBe('6.000000')
        ->and((string) $balance->quantity)->toBe('10.000000')
        ->and((string) $balance->reserved_quantity)->toBe('6.000000')
        ->and((string) $balance->available_quantity)->toBe('4.000000')
        ->and(StockMovement::query()->where('company_id', $company->getKey())->count())->toBe(1);

    $progress = app(SalesOrderProgressService::class);
    DB::transaction(fn (): SalesOrderLineProgressEffect => $progress->record(
        m67Identity($company, 'dispatch-2', 'progress.dispatch'),
        (int) $line->getKey(),
        SalesOrderProgressType::Dispatched,
        '2',
    ));
    DB::transaction(fn (): SalesOrderLineProgressEffect => $progress->record(
        m67Identity($company, 'invoice-1', 'progress.invoice'),
        (int) $line->getKey(),
        SalesOrderProgressType::Invoiced,
        '1',
    ));
    $cancel = DB::transaction(fn (): SalesOrderLineProgressEffect => $progress->record(
        m67Identity($company, 'cancel-1', 'progress.cancel'),
        (int) $line->getKey(),
        SalesOrderProgressType::Cancelled,
        '1',
    ));

    $beforeReopen = $line->fresh()->progress()->firstOrFail();
    expect((string) $beforeReopen->ordered_quantity)->toBe('6.000000')
        ->and((string) $beforeReopen->net_dispatched_quantity)->toBe('2.000000')
        ->and((string) $beforeReopen->net_invoiced_quantity)->toBe('1.000000')
        ->and((string) $beforeReopen->cancelled_quantity)->toBe('1.000000')
        ->and((string) $beforeReopen->dispatch_remaining_quantity)->toBe('3.000000')
        ->and((string) $beforeReopen->invoice_remaining_quantity)->toBe('4.000000')
        ->and((string) $beforeReopen->remaining_quantity)->toBe('3.000000');

    expect(fn () => DB::transaction(fn (): SalesOrderLineProgressEffect => $progress->record(
        m67Identity($company, 'cancel-over', 'progress.cancel'),
        (int) $line->getKey(),
        SalesOrderProgressType::Cancelled,
        '4',
    )))->toThrow(ValidationException::class);

    DB::transaction(fn (): SalesOrderLineProgressEffect => $progress->reverse(
        m67Identity($company, 'cancel-reopen', 'progress.cancel_reversal'),
        (int) $cancel->getKey(),
    ));

    $reopened = $line->fresh()->progress()->firstOrFail();
    $balance->refresh();
    expect((string) $reopened->cancelled_quantity)->toBe('0.000000')
        ->and((string) $reopened->dispatch_remaining_quantity)->toBe('4.000000')
        ->and((string) $reopened->invoice_remaining_quantity)->toBe('5.000000')
        ->and((string) $reopened->remaining_quantity)->toBe('4.000000')
        ->and(StockMovement::query()->where('company_id', $company->getKey())->count())->toBe(1)
        ->and((string) $balance->quantity)->toBe('10.000000')
        ->and((string) $balance->reserved_quantity)->toBe('6.000000');

    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->get('/sales-orders/'.$order->getKey().'/edit')->assertStatus(409);
    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->get('/sales-orders/'.$order->getKey())
        ->assertOk()
        ->assertSee('KDV Sıfırlandı')
        ->assertSee('Neden: ISTISNA')
        ->assertSee('6.000000')
        ->assertSee('2.000000')
        ->assertSee('1.000000')
        ->assertSee('4.000000')
        ->assertDontSee('Düzenle');
});

/** @return array{Company, Account, Product, TaxZeroReason, Warehouse, WarehouseLocation, User} */
function m67Fixture(): array
{
    $company = Company::query()->create(['code' => 'M67-EXIT', 'name' => 'M6.7 Exit Gate Company']);
    $account = Account::query()->create([
        'company_id' => $company->getKey(), 'code' => 'CUST', 'type' => AccountType::Customer,
        'status' => AccountStatus::Active, 'legal_name' => 'M6.7 Müşterisi', 'trade_name' => null,
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
    $reason = TaxZeroReason::query()->create([
        'company_id' => $company->getKey(), 'code' => 'ISTISNA', 'name' => 'İstisna', 'is_active' => true,
    ]);
    $product = Product::query()->create([
        'company_id' => $company->getKey(), 'code' => 'M67-SKU', 'status' => ProductStatus::Active,
        'name' => 'M6.7 Ürünü', 'category_id' => $category->getKey(), 'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(), 'sale_price_net' => '100.000000', 'purchase_price_net' => '60.000000',
    ]);
    $warehouse = Warehouse::query()->create([
        'company_id' => $company->getKey(), 'code' => 'MAIN', 'name' => 'Merkez Depo', 'is_active' => true,
    ]);
    $location = WarehouseLocation::query()->create([
        'company_id' => $company->getKey(), 'warehouse_id' => $warehouse->getKey(),
        'code' => 'A-01', 'name' => 'A Rafı', 'is_active' => true,
    ]);
    DocumentSequence::query()->create([
        'company_id' => $company->getKey(), 'document_type' => DocumentType::SalesOrder->value,
        'series_code' => 'default', 'prefix' => 'SO-', 'padding' => 4, 'next_value' => 1, 'is_active' => true,
    ]);
    $manager = User::query()->create([
        'name' => 'M67 Manager', 'email' => 'm67-manager@example.test',
        'password' => 'correct-password', 'status' => UserStatus::Active,
    ]);
    $membership = CompanyMembership::query()->create([
        'company_id' => $company->getKey(), 'user_id' => $manager->getKey(), 'is_active' => true, 'joined_at' => now(),
    ]);
    $role = Role::query()->create([
        'company_id' => $company->getKey(), 'code' => 'ORDER-MANAGER', 'name' => 'Sipariş Yöneticisi', 'is_active' => true,
    ]);
    app(GrantPermissionToRole::class)->handle($role, PermissionKey::SalesOrderView);
    app(GrantPermissionToRole::class)->handle($role, PermissionKey::SalesOrderManage);
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return [$company, $account, $product, $reason, $warehouse, $location, $manager];
}

function m67Opening(Company $company, Product $product, Warehouse $warehouse, WarehouseLocation $location, string $quantity): void
{
    DB::transaction(fn () => app(StockMovementPoster::class)->post(new PostStockMovementData(
        sourceEffect: new SourceEffectIdentity(
            (int) $company->getKey(),
            'sales_order.exit_gate',
            'opening',
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

function m67Identity(Company $company, string $sourceId, string $effectType): SourceEffectIdentity
{
    return new SourceEffectIdentity(
        (int) $company->getKey(),
        'sales_order.exit_gate',
        $sourceId,
        $effectType,
    );
}

/** @return array<string, mixed> */
function m67Payload(
    Account $account,
    Product $product,
    TaxZeroReason $reason,
    Warehouse $warehouse,
    WarehouseLocation $location,
    string $quantity,
    ?string $logicalLineKey = null,
): array {
    return [
        'series_code' => 'default',
        'account_id' => $account->getKey(),
        'order_date' => '2026-08-26',
        'currency_code' => 'TRY',
        'document_discount_rate' => '0',
        'note' => 'M6.7 exit gate',
        'lines' => [[
            'logical_line_key' => $logicalLineKey,
            'product_id' => $product->getKey(),
            'warehouse_id' => $warehouse->getKey(),
            'location_id' => $location->getKey(),
            'description' => 'M6.7 line',
            'quantity' => $quantity,
            'unit_price' => '100',
            'price_basis' => 'net',
            'line_discount_rate' => '0',
            'tax_is_zeroed' => '1',
            'tax_zero_reason_id' => $reason->getKey(),
        ]],
    ];
}
