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
use App\Modules\Core\Enums\PostingPeriodStatus;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\DocumentSequence;
use App\Modules\Core\Models\PostingPeriod;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\Tax;
use App\Modules\Core\Models\User;
use App\Modules\Dispatches\Models\Dispatch;
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
use App\Modules\SalesInvoices\Enums\SalesInvoiceMode;
use App\Modules\SalesInvoices\Enums\SalesInvoiceStatus;
use App\Modules\SalesInvoices\Models\SalesInvoice;
use App\Modules\SalesOrders\Enums\SalesOrderProgressType;
use App\Modules\SalesOrders\Models\SalesOrder;
use App\Modules\SalesOrders\Models\SalesOrderLineProgressEffect;
use App\Modules\SalesOrders\Models\SalesOrderReservationGeneration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('posts and reverses direct invoice stock exactly once', function (): void {
    [$company, $account, $product, $billing, , $warehouse, $location, $manager] = m85Fixture('M85-DIRECT');
    m85Opening($company, $product, $warehouse, $location, '10');
    $invoice = m85DirectInvoice($this, $company, $manager, $account, $product, $billing, $warehouse, $location, '2');

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('sales-invoices.finalize', $invoice->getKey()))
        ->assertRedirect(route('sales-invoices.show', $invoice->getKey()));

    $line = $invoice->lines()->firstOrFail();
    $movement = StockMovement::query()
        ->where('source_type', 'sales_invoice_line')
        ->where('source_id', (string) $line->getKey())
        ->where('effect_type', 'stock.out')
        ->firstOrFail();
    $balance = StockBalance::query()->where('product_id', $product->getKey())->firstOrFail();

    expect($invoice->refresh()->statusEnum())->toBe(SalesInvoiceStatus::Finalized)
        ->and($movement->movement_type)->toBe(StockMovementType::InvoiceOut)
        ->and((string) $movement->quantity_delta)->toBe('-2.000000')
        ->and((string) $balance->refresh()->quantity)->toBe('8.000000');

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('sales-invoices.finalize', $invoice->getKey()))
        ->assertRedirect();

    expect(StockMovement::query()->where('movement_type', StockMovementType::InvoiceOut->value)->count())->toBe(1);

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('sales-invoices.cancel', $invoice->getKey()))
        ->assertRedirect(route('sales-invoices.show', $invoice->getKey()));

    $reversal = StockMovement::query()
        ->where('source_type', 'sales_invoice_line')
        ->where('source_id', (string) $line->getKey())
        ->where('effect_type', 'stock.out.reverse')
        ->firstOrFail();

    expect($invoice->refresh()->statusEnum())->toBe(SalesInvoiceStatus::Cancelled)
        ->and($reversal->movement_type)->toBe(StockMovementType::AdjustmentIn)
        ->and((string) $reversal->quantity_delta)->toBe('2.000000')
        ->and((int) $reversal->reversal_of_movement_id)->toBe((int) $movement->getKey())
        ->and((string) $balance->refresh()->quantity)->toBe('10.000000');

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('sales-invoices.cancel', $invoice->getKey()))
        ->assertRedirect();

    expect(StockMovement::query()->where('source_type', 'sales_invoice_line')->count())->toBe(2);
});

it('converts a partial order reservation into invoice stock out and exact remainder then restores it on cancel', function (): void {
    [$company, $account, $product, $billing, , $warehouse, $location, $manager] = m85Fixture('M85-ORDER');
    m85Opening($company, $product, $warehouse, $location, '10');
    $order = m85Order($this, $company, $manager, $account, $product, $warehouse, $location, '10');
    $oldGeneration = SalesOrderReservationGeneration::query()->firstOrFail();
    $oldReservation = StockReservation::query()->findOrFail($oldGeneration->stock_reservation_id);
    $invoice = m85OrderInvoice($this, $company, $manager, $order, $billing, $warehouse, $location, '4');

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('sales-invoices.finalize', $invoice->getKey()))
        ->assertRedirect(route('sales-invoices.show', $invoice->getKey()));

    $invoiceLine = $invoice->lines()->firstOrFail();
    $generations = SalesOrderReservationGeneration::query()->orderBy('generation')->get();
    $activeGeneration = $generations->last();
    $activeReservation = StockReservation::query()->findOrFail($activeGeneration->stock_reservation_id);
    $balance = StockBalance::query()->where('product_id', $product->getKey())->firstOrFail();
    $invoiceProgress = m85Progress($invoiceLine->getKey(), 'progress.invoice');
    $dispatchProgress = m85Progress($invoiceLine->getKey(), 'progress.dispatch');

    expect($oldReservation->refresh()->statusEnum())->toBe(StockReservationStatus::Released)
        ->and($oldGeneration->refresh()->released_at)->not->toBeNull()
        ->and($generations)->toHaveCount(2)
        ->and((int) $activeGeneration->generation)->toBe(2)
        ->and((string) $activeGeneration->quantity)->toBe('6.000000')
        ->and($activeReservation->statusEnum())->toBe(StockReservationStatus::Active)
        ->and((string) $activeReservation->quantity)->toBe('6.000000')
        ->and((string) $balance->refresh()->quantity)->toBe('6.000000')
        ->and((string) $balance->reserved_quantity)->toBe('6.000000')
        ->and((string) $balance->available_quantity)->toBe('0.000000')
        ->and((string) $invoiceProgress->quantity_delta)->toBe('4.000000')
        ->and($invoiceProgress->progressTypeEnum())->toBe(SalesOrderProgressType::Invoiced)
        ->and((string) $dispatchProgress->quantity_delta)->toBe('4.000000')
        ->and($dispatchProgress->progressTypeEnum())->toBe(SalesOrderProgressType::Dispatched)
        ->and(StockMovement::query()->where('movement_type', StockMovementType::InvoiceOut->value)->count())->toBe(1);

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('sales-invoices.cancel', $invoice->getKey()))
        ->assertRedirect(route('sales-invoices.show', $invoice->getKey()));

    $allGenerations = SalesOrderReservationGeneration::query()->orderBy('generation')->get();
    $restoredGeneration = $allGenerations->last();
    $restoredReservation = StockReservation::query()->findOrFail($restoredGeneration->stock_reservation_id);
    $invoiceProgressReversal = SalesOrderLineProgressEffect::query()
        ->where('reversal_of_progress_effect_id', $invoiceProgress->getKey())
        ->firstOrFail();
    $dispatchProgressReversal = SalesOrderLineProgressEffect::query()
        ->where('reversal_of_progress_effect_id', $dispatchProgress->getKey())
        ->firstOrFail();

    expect($allGenerations)->toHaveCount(3)
        ->and((int) $restoredGeneration->generation)->toBe(3)
        ->and((string) $restoredGeneration->quantity)->toBe('10.000000')
        ->and($restoredReservation->statusEnum())->toBe(StockReservationStatus::Active)
        ->and((string) $balance->refresh()->quantity)->toBe('10.000000')
        ->and((string) $balance->reserved_quantity)->toBe('10.000000')
        ->and((string) $balance->available_quantity)->toBe('0.000000')
        ->and((string) $invoiceProgressReversal->quantity_delta)->toBe('-4.000000')
        ->and((string) $dispatchProgressReversal->quantity_delta)->toBe('-4.000000')
        ->and(StockMovement::query()->where('effect_type', 'stock.out.reverse')->count())->toBe(1);
});

it('reuses dispatch stock out for dispatch-linked invoices and never posts or reverses a second physical effect', function (): void {
    [$company, $account, $product, $billing, $shipping, $warehouse, $location, $manager] = m85Fixture('M85-DISPATCH');
    m85Opening($company, $product, $warehouse, $location, '10');
    $order = m85Order($this, $company, $manager, $account, $product, $warehouse, $location, '10');
    $dispatch = m85Dispatch($this, $company, $manager, $order, $shipping, $warehouse, $location, '4');

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('dispatches.finalize', $dispatch->getKey()))
        ->assertRedirect(route('dispatches.show', $dispatch->getKey()));

    $balance = StockBalance::query()->where('product_id', $product->getKey())->firstOrFail();
    expect(StockMovement::query()->where('movement_type', StockMovementType::DispatchOut->value)->count())->toBe(1)
        ->and((string) $balance->refresh()->quantity)->toBe('6.000000');

    $invoice = m85DispatchInvoice($this, $company, $manager, $dispatch, $billing, '3');
    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('sales-invoices.finalize', $invoice->getKey()))
        ->assertRedirect(route('sales-invoices.show', $invoice->getKey()));

    expect($invoice->refresh()->statusEnum())->toBe(SalesInvoiceStatus::Finalized)
        ->and(StockMovement::query()->where('movement_type', StockMovementType::DispatchOut->value)->count())->toBe(1)
        ->and(StockMovement::query()->where('movement_type', StockMovementType::InvoiceOut->value)->count())->toBe(0)
        ->and((string) $balance->refresh()->quantity)->toBe('6.000000');

    m85DispatchInvoice($this, $company, $manager, $dispatch, $billing, '2')
        ->refresh();
    expect(SalesInvoice::query()->where('company_id', $company->getKey())->count())->toBe(1);

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('sales-invoices.cancel', $invoice->getKey()))
        ->assertRedirect(route('sales-invoices.show', $invoice->getKey()));

    expect($invoice->refresh()->statusEnum())->toBe(SalesInvoiceStatus::Cancelled)
        ->and(StockMovement::query()->where('movement_type', StockMovementType::DispatchOut->value)->count())->toBe(1)
        ->and(StockMovement::query()->where('source_type', 'sales_invoice_line')->count())->toBe(0)
        ->and((string) $balance->refresh()->quantity)->toBe('6.000000');
});

it('rejects forged invoice stock movements that do not exactly match their source line', function (): void {
    [$company, $account, $product, $billing, , $warehouse, $location, $manager] = m85Fixture('M85-DB');
    m85Opening($company, $product, $warehouse, $location, '10');
    $invoice = m85DirectInvoice($this, $company, $manager, $account, $product, $billing, $warehouse, $location, '2');
    $line = $invoice->lines()->firstOrFail();

    expect(fn () => DB::transaction(fn () => app(StockMovementPoster::class)->post(new PostStockMovementData(
        sourceEffect: new SourceEffectIdentity(
            (int) $company->getKey(),
            'sales_invoice_line',
            (string) $line->getKey(),
            'stock.out',
        ),
        productId: (int) $product->getKey(),
        warehouseId: (int) $warehouse->getKey(),
        locationId: (int) $location->getKey(),
        movementType: StockMovementType::InvoiceOut,
        quantity: '1',
    ))))->toThrow(QueryException::class);

    expect(StockMovement::query()->where('movement_type', StockMovementType::InvoiceOut->value)->count())->toBe(0)
        ->and((string) StockBalance::query()->where('product_id', $product->getKey())->firstOrFail()->quantity)->toBe('10.000000');
});

/** @return array{Company,Account,Product,AccountAddress,AccountAddress,Warehouse,WarehouseLocation,User} */
function m85Fixture(string $code): array
{
    $company = Company::query()->create(['code' => $code, 'name' => 'Company '.$code]);
    $account = Account::query()->create([
        'company_id' => $company->getKey(), 'code' => 'CUST', 'type' => AccountType::Customer,
        'status' => AccountStatus::Active, 'legal_name' => 'Müşteri '.$code, 'trade_name' => null,
        'tax_identity_type' => TaxIdentityType::None, 'tax_number' => null, 'tax_office' => null,
        'book_currency_code' => 'TRY', 'due_days' => 0, 'discount_rate' => '0.000000', 'risk_limit' => '0.000000',
    ]);
    $billing = m85Address($company, $account, AccountAddressType::Billing, 'Fatura');
    $shipping = m85Address($company, $account, AccountAddressType::Shipping, 'Sevk');
    $category = Category::query()->create(['company_id' => $company->getKey(), 'code' => 'CAT', 'name' => 'Kategori', 'is_active' => true]);
    $unit = Unit::query()->create(['company_id' => $company->getKey(), 'code' => 'ADET', 'name' => 'Adet', 'is_active' => true]);
    $tax = Tax::query()->create(['company_id' => $company->getKey(), 'code' => 'KDV20', 'name' => 'KDV %20', 'rate' => '20.000000', 'is_active' => true]);
    $product = Product::query()->create([
        'company_id' => $company->getKey(), 'code' => 'SKU-'.$code, 'status' => ProductStatus::Active,
        'name' => 'Ürün '.$code, 'category_id' => $category->getKey(), 'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(), 'sale_price_net' => '100.000000', 'purchase_price_net' => '60.000000',
    ]);
    $warehouse = Warehouse::query()->create(['company_id' => $company->getKey(), 'code' => 'MAIN', 'name' => 'Merkez Depo', 'is_active' => true]);
    $location = WarehouseLocation::query()->create([
        'company_id' => $company->getKey(), 'warehouse_id' => $warehouse->getKey(),
        'code' => 'A-01', 'name' => 'A Rafı', 'is_active' => true,
    ]);

    foreach ([
        [DocumentType::SalesOrder, 'SO-'],
        [DocumentType::Dispatch, 'DSP-'],
        [DocumentType::SalesInvoice, 'INV-'],
    ] as [$type, $prefix]) {
        DocumentSequence::query()->create([
            'company_id' => $company->getKey(), 'document_type' => $type, 'series_code' => 'default',
            'prefix' => $prefix, 'padding' => 4, 'next_value' => 1, 'is_active' => true,
        ]);
    }

    PostingPeriod::query()->create([
        'company_id' => $company->getKey(), 'code' => '2026-08', 'name' => 'Ağustos 2026',
        'starts_on' => '2026-08-01', 'ends_on' => '2026-08-31', 'status' => PostingPeriodStatus::Open,
        'closed_at' => null,
    ]);

    return [$company, $account, $product, $billing, $shipping, $warehouse, $location, m85Actor($company, $code)];
}

function m85Address(Company $company, Account $account, AccountAddressType $type, string $label): AccountAddress
{
    return AccountAddress::query()->create([
        'company_id' => $company->getKey(), 'account_id' => $account->getKey(), 'type' => $type,
        'label' => $label, 'recipient_name' => 'Mars Teslim', 'line1' => 'Mars Cad. 85', 'line2' => null,
        'district' => 'Şişli', 'city' => 'İstanbul', 'postal_code' => '34360', 'country_code' => 'TR', 'is_default' => true,
    ]);
}

function m85Actor(Company $company, string $suffix): User
{
    $user = User::query()->create([
        'name' => 'M85 '.$suffix,
        'email' => strtolower($suffix).'@m85.test',
        'password' => 'correct-password',
        'status' => UserStatus::Active,
    ]);
    $membership = CompanyMembership::query()->create([
        'company_id' => $company->getKey(), 'user_id' => $user->getKey(), 'is_active' => true, 'joined_at' => now(),
    ]);
    $role = Role::query()->create(['company_id' => $company->getKey(), 'code' => 'm85', 'name' => 'M85', 'is_active' => true]);
    foreach ([
        PermissionKey::SalesOrderView,
        PermissionKey::SalesOrderManage,
        PermissionKey::DispatchView,
        PermissionKey::DispatchManage,
        PermissionKey::SalesInvoiceView,
        PermissionKey::SalesInvoiceManage,
    ] as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}

function m85Opening(Company $company, Product $product, Warehouse $warehouse, WarehouseLocation $location, string $quantity): void
{
    DB::transaction(fn () => app(StockMovementPoster::class)->post(new PostStockMovementData(
        sourceEffect: new SourceEffectIdentity((int) $company->getKey(), 'sales_invoice.m85', 'opening-'.$company->code, 'inventory.opening'),
        productId: (int) $product->getKey(),
        warehouseId: (int) $warehouse->getKey(),
        locationId: (int) $location->getKey(),
        movementType: StockMovementType::OpeningIn,
        quantity: $quantity,
        unitCost: '10',
    )));
}

function m85Order(
    TestCase $test,
    Company $company,
    User $manager,
    Account $account,
    Product $product,
    Warehouse $warehouse,
    WarehouseLocation $location,
    string $quantity,
): SalesOrder {
    $test->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])->post('/sales-orders', [
        'series_code' => 'default', 'account_id' => $account->getKey(), 'order_date' => '2026-08-27',
        'currency_code' => 'TRY', 'document_discount_rate' => '0', 'note' => null,
        'lines' => [[
            'logical_line_key' => null, 'product_id' => $product->getKey(),
            'warehouse_id' => $warehouse->getKey(), 'location_id' => $location->getKey(),
            'description' => null, 'quantity' => $quantity, 'unit_price' => '100', 'price_basis' => 'net',
            'line_discount_rate' => '0', 'tax_zero_reason_id' => null,
        ]],
    ])->assertRedirect();

    return SalesOrder::query()->where('company_id', $company->getKey())->latest('id')->firstOrFail();
}

function m85Dispatch(
    TestCase $test,
    Company $company,
    User $manager,
    SalesOrder $order,
    AccountAddress $shipping,
    Warehouse $warehouse,
    WarehouseLocation $location,
    string $quantity,
): Dispatch {
    $orderLine = $order->lines()->firstOrFail();
    $test->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])->post('/dispatches', [
        'series_code' => 'default', 'sales_order_id' => $order->getKey(), 'source_address_id' => $shipping->getKey(),
        'dispatch_date' => '2026-08-27', 'lines' => [[
            'sales_order_line_id' => $orderLine->getKey(), 'quantity' => $quantity,
            'allocation_key' => $warehouse->getKey().':'.$location->getKey(),
        ]],
    ])->assertRedirect();

    return Dispatch::query()->where('company_id', $company->getKey())->latest('id')->firstOrFail();
}

function m85DirectInvoice(
    TestCase $test,
    Company $company,
    User $manager,
    Account $account,
    Product $product,
    AccountAddress $billing,
    Warehouse $warehouse,
    WarehouseLocation $location,
    string $quantity,
): SalesInvoice {
    $test->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])->post('/sales-invoices', [
        'series_code' => 'default', 'mode' => SalesInvoiceMode::Direct->value, 'account_id' => $account->getKey(),
        'source_billing_address_id' => $billing->getKey(), 'invoice_date' => '2026-08-27', 'document_discount_rate' => '0',
        'lines' => [[
            'product_id' => $product->getKey(), 'quantity' => $quantity,
            'allocation_key' => $warehouse->getKey().':'.$location->getKey(), 'unit_price' => '100',
            'price_basis' => 'net', 'line_discount_rate' => '0', 'tax_zero_reason_id' => null,
        ]],
    ])->assertRedirect();

    return SalesInvoice::query()->where('company_id', $company->getKey())->latest('id')->firstOrFail();
}

function m85OrderInvoice(
    TestCase $test,
    Company $company,
    User $manager,
    SalesOrder $order,
    AccountAddress $billing,
    Warehouse $warehouse,
    WarehouseLocation $location,
    string $quantity,
): SalesInvoice {
    $orderLine = $order->lines()->firstOrFail();
    $test->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])->post('/sales-invoices', [
        'series_code' => 'default', 'mode' => SalesInvoiceMode::OrderLinked->value,
        'sales_order_id' => $order->getKey(), 'source_billing_address_id' => $billing->getKey(), 'invoice_date' => '2026-08-27',
        'lines' => [[
            'sales_order_line_id' => $orderLine->getKey(), 'quantity' => $quantity,
            'allocation_key' => $warehouse->getKey().':'.$location->getKey(),
        ]],
    ])->assertRedirect();

    return SalesInvoice::query()->where('company_id', $company->getKey())->latest('id')->firstOrFail();
}

function m85DispatchInvoice(
    TestCase $test,
    Company $company,
    User $manager,
    Dispatch $dispatch,
    AccountAddress $billing,
    string $quantity,
): SalesInvoice {
    $dispatchLine = $dispatch->lines()->firstOrFail();
    $test->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])->post('/sales-invoices', [
        'series_code' => 'default', 'mode' => SalesInvoiceMode::DispatchLinked->value,
        'dispatch_id' => $dispatch->getKey(), 'source_billing_address_id' => $billing->getKey(), 'invoice_date' => '2026-08-27',
        'lines' => [[
            'dispatch_line_id' => $dispatchLine->getKey(), 'quantity' => $quantity,
        ]],
    ])->assertRedirect();

    return SalesInvoice::query()->where('company_id', $company->getKey())->latest('id')->firstOrFail();
}

function m85Progress(int $invoiceLineId, string $effectType): SalesOrderLineProgressEffect
{
    return SalesOrderLineProgressEffect::query()
        ->where('source_type', 'sales_invoice_line')
        ->where('source_id', (string) $invoiceLineId)
        ->where('effect_type', $effectType)
        ->firstOrFail();
}
