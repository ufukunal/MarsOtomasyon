<?php

use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Accounts\Enums\AccountAddressType;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountAddress;
use App\Modules\Accounts\Models\AccountTransaction;
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
use App\Modules\SalesOrders\Models\SalesOrder;
use App\Modules\SalesOrders\Models\SalesOrderLineProgressEffect;
use App\Modules\SalesOrders\Models\SalesOrderReservationGeneration;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use Tests\TestCase;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('keeps direct invoice account and stock ledgers exactly reconciled across lifecycle replays', function (): void {
    [$company, $account, $product, $billing, , $warehouse, $location, $manager] = m87Fixture('M87-DIRECT');
    m87Opening($company, $product, $warehouse, $location, '10');
    $invoice = m87DirectInvoice($this, $company, $manager, $account, $product, $billing, $warehouse, $location, '2');

    foreach (range(1, 2) as $_) {
        $this->actingAs($manager)
            ->withSession(['active_company_id' => $company->getKey()])
            ->post(route('sales-invoices.finalize', $invoice->getKey()))
            ->assertRedirect(route('sales-invoices.show', $invoice->getKey()));
    }

    $line = $invoice->lines()->firstOrFail();

    expect($invoice->refresh()->statusEnum())->toBe(SalesInvoiceStatus::Finalized)
        ->and(m87AccountEffects($invoice)->count())->toBe(1)
        ->and(m87InvoiceStockEffects((int) $line->getKey())->count())->toBe(1)
        ->and(m87InvoiceProgressEffects((int) $line->getKey())->count())->toBe(0);

    foreach (range(1, 2) as $_) {
        $this->actingAs($manager)
            ->withSession(['active_company_id' => $company->getKey()])
            ->post(route('sales-invoices.cancel', $invoice->getKey()))
            ->assertRedirect(route('sales-invoices.show', $invoice->getKey()));
    }

    $accountEffects = m87AccountEffects($invoice);
    $stockEffects = m87InvoiceStockEffects((int) $line->getKey());

    expect($invoice->refresh()->statusEnum())->toBe(SalesInvoiceStatus::Cancelled)
        ->and($accountEffects->count())->toBe(2)
        ->and($stockEffects->count())->toBe(2)
        ->and($accountEffects->where('effect_type', 'account.sales_invoice')->count())->toBe(1)
        ->and($accountEffects->where('effect_type', 'account.sales_invoice.reverse')->count())->toBe(1)
        ->and($stockEffects->where('effect_type', 'stock.out')->count())->toBe(1)
        ->and($stockEffects->where('effect_type', 'stock.out.reverse')->count())->toBe(1);
});

it('reconciles order-linked account stock progress and reservation projections as one exit contract', function (): void {
    [$company, $account, $product, $billing, , $warehouse, $location, $manager] = m87Fixture('M87-ORDER');
    m87Opening($company, $product, $warehouse, $location, '10');
    $order = m87Order($this, $company, $manager, $account, $product, $warehouse, $location, '10');
    $invoice = m87OrderInvoice($this, $company, $manager, $order, $billing, $warehouse, $location, '4');

    foreach (range(1, 2) as $_) {
        $this->actingAs($manager)
            ->withSession(['active_company_id' => $company->getKey()])
            ->post(route('sales-invoices.finalize', $invoice->getKey()))
            ->assertRedirect(route('sales-invoices.show', $invoice->getKey()));
    }

    $line = $invoice->lines()->firstOrFail();
    $activeGeneration = SalesOrderReservationGeneration::query()
        ->where('company_id', $company->getKey())
        ->where('sales_order_id', $order->getKey())
        ->whereNull('released_at')
        ->sole();
    $activeReservation = StockReservation::query()->findOrFail($activeGeneration->stock_reservation_id);

    expect($invoice->refresh()->statusEnum())->toBe(SalesInvoiceStatus::Finalized)
        ->and(m87AccountEffects($invoice)->count())->toBe(1)
        ->and(m87InvoiceStockEffects((int) $line->getKey())->count())->toBe(1)
        ->and(m87InvoiceProgressEffects((int) $line->getKey())->count())->toBe(2)
        ->and((string) $activeGeneration->quantity)->toBe('6.000000')
        ->and($activeReservation->statusEnum())->toBe(StockReservationStatus::Active)
        ->and((string) $activeReservation->quantity)->toBe('6.000000');

    foreach (range(1, 2) as $_) {
        $this->actingAs($manager)
            ->withSession(['active_company_id' => $company->getKey()])
            ->post(route('sales-invoices.cancel', $invoice->getKey()))
            ->assertRedirect(route('sales-invoices.show', $invoice->getKey()));
    }

    $restoredGeneration = SalesOrderReservationGeneration::query()
        ->where('company_id', $company->getKey())
        ->where('sales_order_id', $order->getKey())
        ->whereNull('released_at')
        ->sole();
    $restoredReservation = StockReservation::query()->findOrFail($restoredGeneration->stock_reservation_id);

    expect($invoice->refresh()->statusEnum())->toBe(SalesInvoiceStatus::Cancelled)
        ->and(m87AccountEffects($invoice)->count())->toBe(2)
        ->and(m87InvoiceStockEffects((int) $line->getKey())->count())->toBe(2)
        ->and(m87InvoiceProgressEffects((int) $line->getKey())->count())->toBe(4)
        ->and((string) $restoredGeneration->quantity)->toBe('10.000000')
        ->and($restoredReservation->statusEnum())->toBe(StockReservationStatus::Active)
        ->and((string) $restoredReservation->quantity)->toBe('10.000000');
});

it('reconciles dispatch-linked invoices without creating a second physical stock ledger', function (): void {
    [$company, $account, $product, $billing, $shipping, $warehouse, $location, $manager] = m87Fixture('M87-DISPATCH');
    m87Opening($company, $product, $warehouse, $location, '10');
    $order = m87Order($this, $company, $manager, $account, $product, $warehouse, $location, '10');
    $dispatch = m87Dispatch($this, $company, $manager, $order, $shipping, $warehouse, $location, '4');

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('dispatches.finalize', $dispatch->getKey()))
        ->assertRedirect(route('dispatches.show', $dispatch->getKey()));

    $invoice = m87DispatchInvoice($this, $company, $manager, $dispatch, $billing, '3');

    foreach (range(1, 2) as $_) {
        $this->actingAs($manager)
            ->withSession(['active_company_id' => $company->getKey()])
            ->post(route('sales-invoices.finalize', $invoice->getKey()))
            ->assertRedirect(route('sales-invoices.show', $invoice->getKey()));
    }

    $line = $invoice->lines()->firstOrFail();

    expect($invoice->refresh()->statusEnum())->toBe(SalesInvoiceStatus::Finalized)
        ->and(m87AccountEffects($invoice)->count())->toBe(1)
        ->and(m87InvoiceStockEffects((int) $line->getKey())->count())->toBe(0)
        ->and(m87InvoiceProgressEffects((int) $line->getKey())->count())->toBe(1)
        ->and(StockMovement::query()->where('movement_type', StockMovementType::DispatchOut->value)->count())->toBe(1)
        ->and(StockMovement::query()->where('movement_type', StockMovementType::InvoiceOut->value)->count())->toBe(0);

    foreach (range(1, 2) as $_) {
        $this->actingAs($manager)
            ->withSession(['active_company_id' => $company->getKey()])
            ->post(route('sales-invoices.cancel', $invoice->getKey()))
            ->assertRedirect(route('sales-invoices.show', $invoice->getKey()));
    }

    expect($invoice->refresh()->statusEnum())->toBe(SalesInvoiceStatus::Cancelled)
        ->and(m87AccountEffects($invoice)->count())->toBe(2)
        ->and(m87InvoiceStockEffects((int) $line->getKey())->count())->toBe(0)
        ->and(m87InvoiceProgressEffects((int) $line->getKey())->count())->toBe(2)
        ->and(StockMovement::query()->where('movement_type', StockMovementType::DispatchOut->value)->count())->toBe(1);
});

it('serializes competing invoice lifecycle writers on the invoice row before posting effects', function (): void {
    [$company, $account, $product, $billing, , $warehouse, $location, $manager] = m87Fixture('M87-LOCK');
    m87Opening($company, $product, $warehouse, $location, '10');
    $invoice = m87DirectInvoice($this, $company, $manager, $account, $product, $billing, $warehouse, $location, '2');

    $connection = DB::connection();
    $config = config('database.connections.pgsql');
    expect($config)->toBeArray();

    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        (string) ($config['host'] ?? '127.0.0.1'),
        (string) ($config['port'] ?? '5432'),
        (string) ($config['database'] ?? ''),
    );
    $competitor = new PDO(
        $dsn,
        (string) ($config['username'] ?? ''),
        (string) ($config['password'] ?? ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );

    $connection->beginTransaction();

    try {
        SalesInvoice::query()->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();
        $competitor->beginTransaction();
        $competitor->exec("SET LOCAL lock_timeout = '150ms'");

        try {
            $statement = $competitor->prepare('SELECT id FROM sales_invoices WHERE id = :id FOR UPDATE');
            $statement->execute(['id' => $invoice->getKey()]);
            throw new RuntimeException('Competing lifecycle writer unexpectedly acquired the invoice row lock.');
        } catch (PDOException $exception) {
            expect($exception->getCode())->toBe('55P03');
        } finally {
            if ($competitor->inTransaction()) {
                $competitor->rollBack();
            }
        }
    } finally {
        if ($connection->transactionLevel() > 0) {
            $connection->rollBack();
        }
    }

    foreach (range(1, 2) as $_) {
        $this->actingAs($manager)
            ->withSession(['active_company_id' => $company->getKey()])
            ->post(route('sales-invoices.finalize', $invoice->getKey()))
            ->assertRedirect(route('sales-invoices.show', $invoice->getKey()));
    }

    $line = $invoice->lines()->firstOrFail();
    expect(m87AccountEffects($invoice)->count())->toBe(1)
        ->and(m87InvoiceStockEffects((int) $line->getKey())->count())->toBe(1);
});

/** @return array{Company,Account,Product,AccountAddress,AccountAddress,Warehouse,WarehouseLocation,User} */
function m87Fixture(string $code): array
{
    $company = Company::query()->create(['code' => $code, 'name' => 'Company '.$code]);
    $account = Account::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'CUST',
        'type' => AccountType::Customer,
        'status' => AccountStatus::Active,
        'legal_name' => 'Müşteri '.$code,
        'trade_name' => null,
        'tax_identity_type' => TaxIdentityType::None,
        'tax_number' => null,
        'tax_office' => null,
        'book_currency_code' => 'TRY',
        'due_days' => 0,
        'discount_rate' => '0.000000',
        'risk_limit' => '0.000000',
    ]);
    $billing = m87Address($company, $account, AccountAddressType::Billing, 'Fatura');
    $shipping = m87Address($company, $account, AccountAddressType::Shipping, 'Sevk');
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
        'code' => 'SKU-'.$code,
        'status' => ProductStatus::Active,
        'name' => 'Ürün '.$code,
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

    foreach ([
        [DocumentType::SalesOrder, 'SO-'],
        [DocumentType::Dispatch, 'DSP-'],
        [DocumentType::SalesInvoice, 'INV-'],
    ] as [$type, $prefix]) {
        DocumentSequence::query()->create([
            'company_id' => $company->getKey(),
            'document_type' => $type,
            'series_code' => 'default',
            'prefix' => $prefix,
            'padding' => 4,
            'next_value' => 1,
            'is_active' => true,
        ]);
    }

    PostingPeriod::query()->create([
        'company_id' => $company->getKey(),
        'code' => '2026-08',
        'name' => 'Ağustos 2026',
        'starts_on' => '2026-08-01',
        'ends_on' => '2026-08-31',
        'status' => PostingPeriodStatus::Open,
        'closed_at' => null,
    ]);

    return [$company, $account, $product, $billing, $shipping, $warehouse, $location, m87Actor($company, $code)];
}

function m87Address(Company $company, Account $account, AccountAddressType $type, string $label): AccountAddress
{
    return AccountAddress::query()->create([
        'company_id' => $company->getKey(),
        'account_id' => $account->getKey(),
        'type' => $type,
        'label' => $label,
        'recipient_name' => 'Mars Teslim',
        'line1' => 'Mars Cad. 87',
        'line2' => null,
        'district' => 'Şişli',
        'city' => 'İstanbul',
        'postal_code' => '34360',
        'country_code' => 'TR',
        'is_default' => true,
    ]);
}

function m87Actor(Company $company, string $suffix): User
{
    $user = User::query()->create([
        'name' => 'M87 '.$suffix,
        'email' => strtolower($suffix).'@m87.test',
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
        'code' => 'm87',
        'name' => 'M87',
        'is_active' => true,
    ]);

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

function m87Opening(Company $company, Product $product, Warehouse $warehouse, WarehouseLocation $location, string $quantity): void
{
    DB::transaction(fn () => app(StockMovementPoster::class)->post(new PostStockMovementData(
        sourceEffect: new SourceEffectIdentity(
            (int) $company->getKey(),
            'sales_invoice.m87',
            'opening-'.$company->code,
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

function m87Order(
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
        'series_code' => 'default',
        'account_id' => $account->getKey(),
        'order_date' => '2026-08-27',
        'currency_code' => 'TRY',
        'document_discount_rate' => '0',
        'note' => null,
        'lines' => [[
            'logical_line_key' => null,
            'product_id' => $product->getKey(),
            'warehouse_id' => $warehouse->getKey(),
            'location_id' => $location->getKey(),
            'description' => null,
            'quantity' => $quantity,
            'unit_price' => '100',
            'price_basis' => 'net',
            'line_discount_rate' => '0',
            'tax_zero_reason_id' => null,
        ]],
    ])->assertRedirect();

    return SalesOrder::query()->where('company_id', $company->getKey())->latest('id')->firstOrFail();
}

function m87Dispatch(
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
        'series_code' => 'default',
        'sales_order_id' => $order->getKey(),
        'source_address_id' => $shipping->getKey(),
        'dispatch_date' => '2026-08-27',
        'lines' => [[
            'sales_order_line_id' => $orderLine->getKey(),
            'quantity' => $quantity,
            'allocation_key' => $warehouse->getKey().':'.$location->getKey(),
        ]],
    ])->assertRedirect();

    return Dispatch::query()->where('company_id', $company->getKey())->latest('id')->firstOrFail();
}

function m87DirectInvoice(
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
        'series_code' => 'default',
        'mode' => SalesInvoiceMode::Direct->value,
        'account_id' => $account->getKey(),
        'source_billing_address_id' => $billing->getKey(),
        'invoice_date' => '2026-08-27',
        'document_discount_rate' => '0',
        'lines' => [[
            'product_id' => $product->getKey(),
            'quantity' => $quantity,
            'allocation_key' => $warehouse->getKey().':'.$location->getKey(),
            'unit_price' => '100',
            'price_basis' => 'net',
            'line_discount_rate' => '0',
            'tax_zero_reason_id' => null,
        ]],
    ])->assertRedirect();

    return SalesInvoice::query()->where('company_id', $company->getKey())->latest('id')->firstOrFail();
}

function m87OrderInvoice(
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
        'series_code' => 'default',
        'mode' => SalesInvoiceMode::OrderLinked->value,
        'sales_order_id' => $order->getKey(),
        'source_billing_address_id' => $billing->getKey(),
        'invoice_date' => '2026-08-27',
        'lines' => [[
            'sales_order_line_id' => $orderLine->getKey(),
            'quantity' => $quantity,
            'allocation_key' => $warehouse->getKey().':'.$location->getKey(),
        ]],
    ])->assertRedirect();

    return SalesInvoice::query()->where('company_id', $company->getKey())->latest('id')->firstOrFail();
}

function m87DispatchInvoice(
    TestCase $test,
    Company $company,
    User $manager,
    Dispatch $dispatch,
    AccountAddress $billing,
    string $quantity,
): SalesInvoice {
    $dispatchLine = $dispatch->lines()->firstOrFail();
    $test->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])->post('/sales-invoices', [
        'series_code' => 'default',
        'mode' => SalesInvoiceMode::DispatchLinked->value,
        'dispatch_id' => $dispatch->getKey(),
        'source_billing_address_id' => $billing->getKey(),
        'invoice_date' => '2026-08-27',
        'lines' => [[
            'dispatch_line_id' => $dispatchLine->getKey(),
            'quantity' => $quantity,
        ]],
    ])->assertRedirect();

    return SalesInvoice::query()->where('company_id', $company->getKey())->latest('id')->firstOrFail();
}

function m87AccountEffects(SalesInvoice $invoice)
{
    return AccountTransaction::query()
        ->where('company_id', $invoice->company_id)
        ->where('source_type', 'sales_invoice')
        ->where('source_id', (string) $invoice->getKey())
        ->orderBy('id')
        ->get();
}

function m87InvoiceStockEffects(int $invoiceLineId)
{
    return StockMovement::query()
        ->where('source_type', 'sales_invoice_line')
        ->where('source_id', (string) $invoiceLineId)
        ->orderBy('id')
        ->get();
}

function m87InvoiceProgressEffects(int $invoiceLineId)
{
    return SalesOrderLineProgressEffect::query()
        ->where('source_type', 'sales_invoice_line')
        ->where('source_id', (string) $invoiceLineId)
        ->orderBy('id')
        ->get();
}
