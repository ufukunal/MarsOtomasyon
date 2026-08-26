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
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Ledger\PostStockMovementData;
use App\Modules\Inventory\Ledger\StockMovementPoster;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\SalesInvoices\Enums\SalesInvoiceMode;
use App\Modules\SalesInvoices\Models\SalesInvoice;
use App\Modules\SalesOrders\Models\SalesOrder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('creates a direct draft invoice with immutable legal/address/product snapshots and no business effects', function (): void {
    [$company, $account, $product, $billing, , $warehouse, $location] = invoice81Fixture('INV81-DIR');
    $manager = invoice81Actor($company, [PermissionKey::SalesInvoiceView, PermissionKey::SalesInvoiceManage], 'direct');

    $stockBefore = DB::table('stock_movements')->count();
    $progressBefore = DB::table('sales_order_line_progress_effects')->count();
    $accountBefore = DB::table('account_transactions')->count();

    $response = $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->post('/sales-invoices', [
            'series_code' => 'default',
            'mode' => SalesInvoiceMode::Direct->value,
            'account_id' => $account->getKey(),
            'source_billing_address_id' => $billing->getKey(),
            'invoice_date' => '2026-08-26',
            'note' => 'Direct M8.1',
            'lines' => [[
                'product_id' => $product->getKey(),
                'quantity' => '2.5',
                'allocation_key' => $warehouse->getKey().':'.$location->getKey(),
            ]],
        ]);

    $invoice = SalesInvoice::query()->where('company_id', $company->getKey())->firstOrFail();
    $line = $invoice->lines()->firstOrFail();
    $response->assertRedirect('/sales-invoices/'.$invoice->getKey());

    expect((string) $invoice->number)->toBe('INV-0001')
        ->and($invoice->modeEnum())->toBe(SalesInvoiceMode::Direct)
        ->and($invoice->source_sales_order_id)->toBeNull()
        ->and($invoice->source_dispatch_id)->toBeNull()
        ->and((string) $invoice->currency_code)->toBe('TRY')
        ->and((string) $invoice->customer_legal_name)->toBe('Müşteri INV81-DIR')
        ->and((string) $invoice->customer_trade_name)->toBe('Ticari INV81-DIR')
        ->and((string) $invoice->customer_tax_identity_type)->toBe('vkn')
        ->and((string) $invoice->customer_tax_number)->toBe('1234567890')
        ->and((string) $invoice->address_line1)->toBe('Fatura Cad. 81')
        ->and((string) $invoice->city)->toBe('İstanbul')
        ->and((int) $line->product_id)->toBe((int) $product->getKey())
        ->and((string) $line->product_code)->toBe('SKU')
        ->and((string) $line->product_name)->toBe('Ürün INV81-DIR')
        ->and((string) $line->quantity)->toBe('2.500000')
        ->and((int) $line->warehouse_id)->toBe((int) $warehouse->getKey())
        ->and((int) $line->location_id)->toBe((int) $location->getKey())
        ->and(DB::table('stock_movements')->count())->toBe($stockBefore)
        ->and(DB::table('sales_order_line_progress_effects')->count())->toBe($progressBefore)
        ->and(DB::table('account_transactions')->count())->toBe($accountBefore);

    $account->forceFill(['legal_name' => 'Değişen Ünvan', 'trade_name' => 'Değişen Ticari'])->save();
    $billing->forceFill(['line1' => 'Yeni Fatura Adresi', 'city' => 'Ankara'])->save();
    $product->forceFill(['name' => 'Değişen Ürün'])->save();
    $invoice->refresh();
    $line->refresh();

    expect((string) $invoice->customer_legal_name)->toBe('Müşteri INV81-DIR')
        ->and((string) $invoice->customer_trade_name)->toBe('Ticari INV81-DIR')
        ->and((string) $invoice->address_line1)->toBe('Fatura Cad. 81')
        ->and((string) $invoice->city)->toBe('İstanbul')
        ->and((string) $line->product_name)->toBe('Ürün INV81-DIR');

    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->get('/sales-invoices/'.$invoice->getKey())->assertOk()->assertSee('Müşteri INV81-DIR')->assertSee('Fatura Cad. 81');
});

it('creates an order-linked draft with inherited allocation and freezes source order lineage at PostgreSQL', function (): void {
    [$company, $account, $product, $billing, , $warehouse, $location] = invoice81Fixture('INV81-ORD');
    invoice81Opening($company, $product, $warehouse, $location, '5');
    $manager = invoice81Actor($company, [
        PermissionKey::SalesOrderView,
        PermissionKey::SalesOrderManage,
        PermissionKey::SalesInvoiceView,
        PermissionKey::SalesInvoiceManage,
    ], 'order');
    $order = invoice81CreateOrder($this, $company, $manager, $account, $product, $warehouse, $location);
    $sourceLine = $order->lines()->firstOrFail();

    $stockBefore = DB::table('stock_movements')->count();
    $progressBefore = DB::table('sales_order_line_progress_effects')->count();
    $accountBefore = DB::table('account_transactions')->count();

    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->post('/sales-invoices', [
            'mode' => SalesInvoiceMode::OrderLinked->value,
            'sales_order_id' => $order->getKey(),
            'source_billing_address_id' => $billing->getKey(),
            'invoice_date' => '2026-08-26',
            'lines' => [[
                'sales_order_line_id' => $sourceLine->getKey(),
                'quantity' => '1',
            ]],
        ])->assertRedirect();

    $invoice = SalesInvoice::query()->where('company_id', $company->getKey())->firstOrFail();
    $line = $invoice->lines()->firstOrFail();
    expect($invoice->modeEnum())->toBe(SalesInvoiceMode::OrderLinked)
        ->and((int) $invoice->source_sales_order_id)->toBe((int) $order->getKey())
        ->and($invoice->source_dispatch_id)->toBeNull()
        ->and((int) $line->source_sales_order_line_id)->toBe((int) $sourceLine->getKey())
        ->and($line->source_dispatch_line_id)->toBeNull()
        ->and((int) $line->warehouse_id)->toBe((int) $warehouse->getKey())
        ->and((int) $line->location_id)->toBe((int) $location->getKey())
        ->and(DB::table('stock_movements')->count())->toBe($stockBefore)
        ->and(DB::table('sales_order_line_progress_effects')->count())->toBe($progressBefore)
        ->and(DB::table('account_transactions')->count())->toBe($accountBefore);

    expect(fn () => DB::table('sales_orders')->where('id', $order->getKey())->update(['note' => 'raw mutate']))
        ->toThrow(QueryException::class);
    expect(fn () => DB::table('sales_order_lines')->where('id', $sourceLine->getKey())->update(['description' => 'raw mutate']))
        ->toThrow(QueryException::class);

    expect(fn () => DB::table('sales_invoice_lines')->insert([
        'company_id' => $company->getKey(),
        'sales_invoice_id' => $invoice->getKey(),
        'source_sales_order_id' => null,
        'source_sales_order_line_id' => null,
        'source_dispatch_id' => null,
        'source_dispatch_line_id' => null,
        'position' => 2,
        'product_id' => $product->getKey(),
        'warehouse_id' => $warehouse->getKey(),
        'location_id' => $location->getKey(),
        'product_code' => 'SKU',
        'product_name' => 'Ürün INV81-ORD',
        'description' => null,
        'quantity' => '1.000000',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('accepts only finalized dispatch lineage and never creates a second stock or order-progress effect', function (): void {
    [$company, $account, $product, $billing, $shipping, $warehouse, $location] = invoice81Fixture('INV81-DSP');
    invoice81Opening($company, $product, $warehouse, $location, '6');
    $manager = invoice81Actor($company, [
        PermissionKey::SalesOrderView,
        PermissionKey::SalesOrderManage,
        PermissionKey::DispatchView,
        PermissionKey::DispatchManage,
        PermissionKey::SalesInvoiceView,
        PermissionKey::SalesInvoiceManage,
    ], 'dispatch');
    $order = invoice81CreateOrder($this, $company, $manager, $account, $product, $warehouse, $location);
    $sourceOrderLine = $order->lines()->firstOrFail();

    $draftDispatch = invoice81CreateDispatch($this, $company, $manager, $order, $sourceOrderLine, $shipping, '1');
    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->post('/sales-invoices', [
            'mode' => SalesInvoiceMode::DispatchLinked->value,
            'dispatch_id' => $draftDispatch->getKey(),
            'source_billing_address_id' => $billing->getKey(),
            'invoice_date' => '2026-08-26',
            'lines' => [[
                'dispatch_line_id' => $draftDispatch->lines()->firstOrFail()->getKey(),
                'quantity' => '1',
            ]],
        ])->assertSessionHasErrors('dispatch_id');
    expect(SalesInvoice::query()->where('company_id', $company->getKey())->count())->toBe(0);

    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->post('/dispatches/'.$draftDispatch->getKey().'/finalize')->assertRedirect();
    $finalized = $draftDispatch->fresh();
    $dispatchLine = $finalized->lines()->firstOrFail();
    $stockBefore = DB::table('stock_movements')->count();
    $progressBefore = DB::table('sales_order_line_progress_effects')->count();
    $accountBefore = DB::table('account_transactions')->count();

    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])
        ->post('/sales-invoices', [
            'mode' => SalesInvoiceMode::DispatchLinked->value,
            'dispatch_id' => $finalized->getKey(),
            'source_billing_address_id' => $billing->getKey(),
            'invoice_date' => '2026-08-26',
            'lines' => [[
                'dispatch_line_id' => $dispatchLine->getKey(),
                'quantity' => '1',
            ]],
        ])->assertRedirect();

    $invoice = SalesInvoice::query()->where('company_id', $company->getKey())->firstOrFail();
    $line = $invoice->lines()->firstOrFail();
    expect($invoice->modeEnum())->toBe(SalesInvoiceMode::DispatchLinked)
        ->and((int) $invoice->source_sales_order_id)->toBe((int) $order->getKey())
        ->and((int) $invoice->source_dispatch_id)->toBe((int) $finalized->getKey())
        ->and((int) $line->source_sales_order_line_id)->toBe((int) $sourceOrderLine->getKey())
        ->and((int) $line->source_dispatch_line_id)->toBe((int) $dispatchLine->getKey())
        ->and((int) $line->warehouse_id)->toBe((int) $dispatchLine->warehouse_id)
        ->and((int) $line->location_id)->toBe((int) $dispatchLine->location_id)
        ->and(DB::table('stock_movements')->count())->toBe($stockBefore)
        ->and(DB::table('sales_order_line_progress_effects')->count())->toBe($progressBefore)
        ->and(DB::table('account_transactions')->count())->toBe($accountBefore);
});

it('isolates invoices by company and enforces view/manage permissions including the sales landing route', function (): void {
    [$companyA] = invoice81Fixture('INV81-A');
    [$companyB, $accountB, $productB, $billingB, , $warehouseB, $locationB] = invoice81Fixture('INV81-B');
    $managerB = invoice81Actor($companyB, [PermissionKey::SalesInvoiceView, PermissionKey::SalesInvoiceManage], 'manager-b');
    $viewerA = invoice81Actor($companyA, [PermissionKey::SalesInvoiceView], 'viewer-a');
    $noInvoiceA = invoice81Actor($companyA, [PermissionKey::AccountView], 'no-invoice-a');

    $this->actingAs($managerB)->withSession(['active_company_id' => $companyB->getKey()])
        ->post('/sales-invoices', [
            'mode' => SalesInvoiceMode::Direct->value,
            'account_id' => $accountB->getKey(),
            'source_billing_address_id' => $billingB->getKey(),
            'invoice_date' => '2026-08-26',
            'lines' => [[
                'product_id' => $productB->getKey(),
                'quantity' => '1',
                'allocation_key' => $warehouseB->getKey().':'.$locationB->getKey(),
            ]],
        ])->assertRedirect();
    $foreign = SalesInvoice::query()->where('company_id', $companyB->getKey())->firstOrFail();

    $this->actingAs($viewerA)->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/sales-invoices')->assertOk()->assertDontSee((string) $foreign->number);
    $this->actingAs($viewerA)->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/sales-invoices/'.$foreign->getKey())->assertNotFound();
    $this->actingAs($viewerA)->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/sales-invoices/create')->assertForbidden();
    $this->actingAs($viewerA)->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/sales')->assertRedirect('/sales-invoices');
    $this->actingAs($viewerA)->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/workspace')->assertOk()->assertSee('Satış');
    $this->actingAs($noInvoiceA)->withSession(['active_company_id' => $companyA->getKey()])
        ->get('/sales-invoices')->assertForbidden();
});

/** @return array{Company,Account,Product,AccountAddress,AccountAddress,Warehouse,WarehouseLocation} */
function invoice81Fixture(string $code): array
{
    $company = Company::query()->create(['code' => $code, 'name' => 'Company '.$code]);
    $account = Account::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'CUST',
        'type' => AccountType::Customer,
        'status' => AccountStatus::Active,
        'legal_name' => 'Müşteri '.$code,
        'trade_name' => 'Ticari '.$code,
        'tax_identity_type' => TaxIdentityType::Vkn,
        'tax_number' => '1234567890',
        'tax_office' => 'Mars VD',
        'book_currency_code' => 'TRY',
        'due_days' => 0,
        'discount_rate' => '0.000000',
        'risk_limit' => '0.000000',
    ]);
    $billing = AccountAddress::query()->create([
        'company_id' => $company->getKey(),
        'account_id' => $account->getKey(),
        'type' => AccountAddressType::Billing,
        'label' => 'Ana Fatura',
        'recipient_name' => 'Muhasebe',
        'line1' => 'Fatura Cad. 81',
        'line2' => 'Kat 8',
        'district' => 'Şişli',
        'city' => 'İstanbul',
        'postal_code' => '34360',
        'country_code' => 'TR',
        'is_default' => true,
    ]);
    $shipping = AccountAddress::query()->create([
        'company_id' => $company->getKey(),
        'account_id' => $account->getKey(),
        'type' => AccountAddressType::Shipping,
        'label' => 'Ana Sevk',
        'recipient_name' => 'Depo',
        'line1' => 'Sevk Cad. 81',
        'line2' => null,
        'district' => 'Şişli',
        'city' => 'İstanbul',
        'postal_code' => '34360',
        'country_code' => 'TR',
        'is_default' => true,
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
        'company_id' => $company->getKey(),
        'code' => 'SKU',
        'status' => ProductStatus::Active,
        'name' => 'Ürün '.$code,
        'category_id' => $category->getKey(),
        'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(),
        'sale_price_net' => '100.000000',
        'purchase_price_net' => '60.000000',
    ]);
    $warehouse = Warehouse::query()->create([
        'company_id' => $company->getKey(), 'code' => 'WH', 'name' => 'Ana Depo', 'is_active' => true,
    ]);
    $location = WarehouseLocation::query()->create([
        'company_id' => $company->getKey(), 'warehouse_id' => $warehouse->getKey(),
        'code' => 'LOC', 'name' => 'Ana Konum', 'is_active' => true,
    ]);
    foreach ([
        [DocumentType::SalesOrder, 'SO-'],
        [DocumentType::Dispatch, 'DSP-'],
        [DocumentType::SalesInvoice, 'INV-'],
    ] as [$documentType, $prefix]) {
        DocumentSequence::query()->create([
            'company_id' => $company->getKey(),
            'document_type' => $documentType,
            'series_code' => 'default',
            'prefix' => $prefix,
            'padding' => 4,
            'next_value' => 1,
            'is_active' => true,
        ]);
    }

    return [$company, $account, $product, $billing, $shipping, $warehouse, $location];
}

function invoice81Opening(
    Company $company,
    Product $product,
    Warehouse $warehouse,
    WarehouseLocation $location,
    string $quantity,
): void {
    DB::transaction(fn () => app(StockMovementPoster::class)->post(new PostStockMovementData(
        sourceEffect: new SourceEffectIdentity(
            (int) $company->getKey(),
            'sales_invoice.m8_1_test',
            'opening-'.$company->getKey(),
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

function invoice81CreateOrder(
    TestCase $test,
    Company $company,
    User $actor,
    Account $account,
    Product $product,
    Warehouse $warehouse,
    WarehouseLocation $location,
): SalesOrder {
    $test->actingAs($actor)->withSession(['active_company_id' => $company->getKey()])
        ->post('/sales-orders', [
            'series_code' => 'default',
            'account_id' => $account->getKey(),
            'order_date' => '2026-08-26',
            'currency_code' => 'TRY',
            'document_discount_rate' => '0',
            'note' => null,
            'lines' => [[
                'product_id' => $product->getKey(),
                'warehouse_id' => $warehouse->getKey(),
                'location_id' => $location->getKey(),
                'description' => 'M8.1 source order line',
                'quantity' => '3',
                'unit_price' => '100',
                'price_basis' => 'net',
                'line_discount_rate' => '0',
                'tax_zero_reason_id' => null,
            ]],
        ])->assertRedirect();

    return SalesOrder::query()->where('company_id', $company->getKey())->orderByDesc('id')->firstOrFail();
}

function invoice81CreateDispatch(
    TestCase $test,
    Company $company,
    User $actor,
    SalesOrder $order,
    $orderLine,
    AccountAddress $shipping,
    string $quantity,
): Dispatch {
    $test->actingAs($actor)->withSession(['active_company_id' => $company->getKey()])
        ->post('/dispatches', [
            'series_code' => 'default',
            'sales_order_id' => $order->getKey(),
            'source_address_id' => $shipping->getKey(),
            'dispatch_date' => '2026-08-26',
            'lines' => [[
                'sales_order_line_id' => $orderLine->getKey(),
                'quantity' => $quantity,
            ]],
        ])->assertRedirect();

    return Dispatch::query()->where('company_id', $company->getKey())->orderByDesc('id')->firstOrFail();
}

/** @param list<PermissionKey> $permissions */
function invoice81Actor(Company $company, array $permissions, string $suffix): User
{
    $user = User::query()->create([
        'name' => 'Invoice '.$suffix,
        'email' => strtolower((string) $company->code).'-'.$suffix.'@invoice.test',
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
        'code' => 'invoice-'.$suffix,
        'name' => 'Invoice '.$suffix,
        'is_active' => true,
    ]);
    foreach ($permissions as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}
