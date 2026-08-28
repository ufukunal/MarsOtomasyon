<?php

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
use App\Modules\GoodsReceipts\Enums\GoodsReceiptStatus;
use App\Modules\GoodsReceipts\Models\GoodsReceipt;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\PurchaseOrders\Enums\PurchaseOrderStatus;
use App\Modules\PurchaseOrders\Models\PurchaseOrder;
use App\Modules\PurchaseOrders\Models\PurchaseOrderLine;
use App\Modules\Quotes\Pricing\PriceBasis;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('finalizes accepted custody into exactly one stock in and purchase order receive progress', function (): void {
    [$company, $supplier, $product, $tax, $warehouse, $location] = goodsReceipt92Fixture('GR92-A');
    $order = goodsReceipt92Order($company, $supplier, $product, $tax, $warehouse, $location, '5');
    $line = $order->lines()->firstOrFail();
    $manager = goodsReceipt92Actor($company, [PermissionKey::GoodsReceiptView, PermissionKey::GoodsReceiptManage, PermissionKey::PurchaseOrderView], 'manager-a');

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/goods-receipts', goodsReceipt92Payload($order, $line, $warehouse, $location, '5', '2', '2', '1'))
        ->assertRedirect();

    $receipt = GoodsReceipt::query()->where('company_id', $company->getKey())->firstOrFail();
    $receiptLine = $receipt->lines()->firstOrFail();

    expect($receipt->statusEnum())->toBe(GoodsReceiptStatus::Draft)
        ->and(DB::table('stock_movements')->count())->toBe(0)
        ->and(DB::table('purchase_order_line_progress_effects')->count())->toBe(0);

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/goods-receipts/'.$receipt->getKey().'/finalize')
        ->assertRedirect('/goods-receipts/'.$receipt->getKey());

    $receipt->refresh();
    $movement = DB::table('stock_movements')
        ->where('company_id', $company->getKey())
        ->where('source_type', 'goods_receipt_line')
        ->where('source_id', (string) $receiptLine->getKey())
        ->where('effect_type', 'stock.in')
        ->first();
    $balance = DB::table('stock_balances')
        ->where('company_id', $company->getKey())
        ->where('product_id', $product->getKey())
        ->where('warehouse_id', $warehouse->getKey())
        ->where('location_id', $location->getKey())
        ->first();
    $progress = DB::table('purchase_order_line_progress')->where('purchase_order_line_id', $line->getKey())->first();
    $custody = DB::table('goods_receipt_line_custody')->where('goods_receipt_line_id', $receiptLine->getKey())->first();

    expect($receipt->statusEnum())->toBe(GoodsReceiptStatus::Finalized)
        ->and($movement)->not->toBeNull()
        ->and((string) $movement->movement_type)->toBe('goods_receipt_in')
        ->and((string) $movement->quantity_delta)->toBe('2.000000')
        ->and((string) $movement->unit_cost)->toBe('100.000000')
        ->and((string) $movement->value_delta)->toBe('200.000000')
        ->and((string) $balance->quantity)->toBe('2.000000')
        ->and((string) $balance->average_unit_cost)->toBe('100.000000')
        ->and((string) $balance->inventory_value)->toBe('200.000000')
        ->and((string) $progress->net_received_quantity)->toBe('2.000000')
        ->and((string) $progress->receive_remaining_quantity)->toBe('3.000000')
        ->and((string) $custody->accepted_quantity)->toBe('2.000000')
        ->and((string) $custody->pending_quantity)->toBe('2.000000')
        ->and((string) $custody->rejected_quantity)->toBe('1.000000');

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/goods-receipts/'.$receipt->getKey().'/finalize')
        ->assertRedirect('/goods-receipts/'.$receipt->getKey());

    expect(DB::table('stock_movements')->where('source_type', 'goods_receipt_line')->where('source_id', (string) $receiptLine->getKey())->count())->toBe(1)
        ->and(DB::table('purchase_order_line_progress_effects')->where('source_type', 'goods_receipt_line')->where('source_id', (string) $receiptLine->getKey())->count())->toBe(1);
});

it('keeps pending and rejected custody unavailable and produces no stock or receive progress', function (): void {
    [$company, $supplier, $product, $tax, $warehouse, $location] = goodsReceipt92Fixture('GR92-B');
    $order = goodsReceipt92Order($company, $supplier, $product, $tax, $warehouse, $location, '5');
    $line = $order->lines()->firstOrFail();
    $manager = goodsReceipt92Actor($company, [PermissionKey::GoodsReceiptView, PermissionKey::GoodsReceiptManage], 'manager-b');

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/goods-receipts', goodsReceipt92Payload($order, $line, $warehouse, $location, '5', '0', '3', '2'))
        ->assertRedirect();

    $receipt = GoodsReceipt::query()->firstOrFail();
    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/goods-receipts/'.$receipt->getKey().'/finalize')
        ->assertRedirect();

    $progress = DB::table('purchase_order_line_progress')->where('purchase_order_line_id', $line->getKey())->first();
    expect($receipt->refresh()->statusEnum())->toBe(GoodsReceiptStatus::Finalized)
        ->and(DB::table('stock_movements')->count())->toBe(0)
        ->and(DB::table('stock_balances')->count())->toBe(0)
        ->and(DB::table('purchase_order_line_progress_effects')->count())->toBe(0)
        ->and((string) $progress->net_received_quantity)->toBe('0.000000')
        ->and((string) $progress->receive_remaining_quantity)->toBe('5.000000');
});

it('blocks concurrent over-acceptance and rolls back its stock effect atomically', function (): void {
    [$company, $supplier, $product, $tax, $warehouse, $location] = goodsReceipt92Fixture('GR92-C');
    $order = goodsReceipt92Order($company, $supplier, $product, $tax, $warehouse, $location, '5');
    $line = $order->lines()->firstOrFail();
    $manager = goodsReceipt92Actor($company, [PermissionKey::GoodsReceiptView, PermissionKey::GoodsReceiptManage], 'manager-c');

    foreach ([1, 2] as $index) {
        $payload = goodsReceipt92Payload($order, $line, $warehouse, $location, '4', '4', '0', '0');
        $payload['series_code'] = 'default';
        $payload['note'] = 'receipt-'.$index;
        $this->actingAs($manager)
            ->withSession(['active_company_id' => $company->getKey()])
            ->post('/goods-receipts', $payload)
            ->assertRedirect();
    }

    $receipts = GoodsReceipt::query()->orderBy('id')->get();
    expect($receipts)->toHaveCount(2);

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/goods-receipts/'.$receipts[0]->getKey().'/finalize')
        ->assertRedirect();

    $secondLine = $receipts[1]->lines()->firstOrFail();
    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/goods-receipts/'.$receipts[1]->getKey().'/finalize')
        ->assertSessionHasErrors('quantity_delta');

    $progress = DB::table('purchase_order_line_progress')->where('purchase_order_line_id', $line->getKey())->first();
    $balance = DB::table('stock_balances')->where('product_id', $product->getKey())->where('warehouse_id', $warehouse->getKey())->where('location_id', $location->getKey())->first();

    expect($receipts[1]->refresh()->statusEnum())->toBe(GoodsReceiptStatus::Draft)
        ->and(DB::table('stock_movements')->where('source_type', 'goods_receipt_line')->where('source_id', (string) $secondLine->getKey())->count())->toBe(0)
        ->and(DB::table('stock_movements')->count())->toBe(1)
        ->and(DB::table('purchase_order_line_progress_effects')->count())->toBe(1)
        ->and((string) $progress->net_received_quantity)->toBe('4.000000')
        ->and((string) $progress->receive_remaining_quantity)->toBe('1.000000')
        ->and((string) $balance->quantity)->toBe('4.000000');
});

it('rejects raw finalization at commit when exact stock and receive effects are missing', function (): void {
    [$company, $supplier, $product, $tax, $warehouse, $location] = goodsReceipt92Fixture('GR92-D');
    $order = goodsReceipt92Order($company, $supplier, $product, $tax, $warehouse, $location, '5');
    $line = $order->lines()->firstOrFail();
    $manager = goodsReceipt92Actor($company, [PermissionKey::GoodsReceiptView, PermissionKey::GoodsReceiptManage], 'manager-d');

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/goods-receipts', goodsReceipt92Payload($order, $line, $warehouse, $location, '2', '2', '0', '0'))
        ->assertRedirect();

    $receipt = GoodsReceipt::query()->firstOrFail();

    expect(fn () => DB::transaction(function () use ($receipt): void {
        DB::table('goods_receipts')->where('id', $receipt->getKey())->update([
            'status' => 'finalized',
            'finalized_at' => now(),
            'updated_at' => now(),
        ]);
    }))->toThrow(QueryException::class);

    expect($receipt->refresh()->statusEnum())->toBe(GoodsReceiptStatus::Draft)
        ->and(DB::table('stock_movements')->count())->toBe(0)
        ->and(DB::table('purchase_order_line_progress_effects')->count())->toBe(0);
});

it('freezes finalized goods receipt header and line custody at PostgreSQL boundary', function (): void {
    [$company, $supplier, $product, $tax, $warehouse, $location] = goodsReceipt92Fixture('GR92-E');
    $order = goodsReceipt92Order($company, $supplier, $product, $tax, $warehouse, $location, '5');
    $line = $order->lines()->firstOrFail();
    $manager = goodsReceipt92Actor($company, [PermissionKey::GoodsReceiptView, PermissionKey::GoodsReceiptManage], 'manager-e');

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/goods-receipts', goodsReceipt92Payload($order, $line, $warehouse, $location, '2', '2', '0', '0'))
        ->assertRedirect();
    $receipt = GoodsReceipt::query()->firstOrFail();
    $receiptLine = $receipt->lines()->firstOrFail();

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/goods-receipts/'.$receipt->getKey().'/finalize')
        ->assertRedirect();

    expect(fn () => DB::table('goods_receipts')->where('id', $receipt->getKey())->update(['note' => 'raw tamper']))
        ->toThrow(QueryException::class);
    expect(fn () => DB::table('goods_receipt_lines')->where('id', $receiptLine->getKey())->update(['accepted_quantity' => '1.000000', 'pending_quantity' => '1.000000']))
        ->toThrow(QueryException::class);
});

/** @return array{Company, Account, Product, Tax, Warehouse, WarehouseLocation} */
function goodsReceipt92Fixture(string $code): array
{
    $company = Company::query()->create(['code' => $code, 'name' => 'Company '.$code]);
    $supplier = Account::query()->create([
        'company_id' => $company->getKey(), 'code' => 'SUP', 'type' => AccountType::Supplier, 'status' => AccountStatus::Active,
        'legal_name' => 'Tedarikçi '.$code, 'trade_name' => null, 'tax_identity_type' => TaxIdentityType::None,
        'tax_number' => null, 'tax_office' => null, 'book_currency_code' => 'TRY', 'due_days' => 0,
        'discount_rate' => '0.000000', 'risk_limit' => '0.000000',
    ]);
    $category = Category::query()->create(['company_id' => $company->getKey(), 'code' => 'CAT', 'name' => 'Kategori', 'is_active' => true]);
    $unit = Unit::query()->create(['company_id' => $company->getKey(), 'code' => 'ADET', 'name' => 'Adet', 'is_active' => true]);
    $tax = Tax::query()->create(['company_id' => $company->getKey(), 'code' => 'KDV20', 'name' => 'KDV %20', 'rate' => '20.000000', 'is_active' => true]);
    $product = Product::query()->create([
        'company_id' => $company->getKey(), 'code' => 'SKU', 'status' => ProductStatus::Active, 'name' => 'Ürün '.$code,
        'category_id' => $category->getKey(), 'unit_id' => $unit->getKey(), 'tax_id' => $tax->getKey(),
        'sale_price_net' => '120.000000', 'purchase_price_net' => '100.000000',
    ]);
    $warehouse = Warehouse::query()->create(['company_id' => $company->getKey(), 'code' => 'WH', 'name' => 'Ana Depo', 'is_active' => true]);
    $location = WarehouseLocation::query()->create(['company_id' => $company->getKey(), 'warehouse_id' => $warehouse->getKey(), 'code' => 'A1', 'name' => 'A1', 'is_active' => true]);

    DocumentSequence::query()->create([
        'company_id' => $company->getKey(), 'document_type' => DocumentType::GoodsReceipt, 'series_code' => 'default',
        'prefix' => 'GR-', 'padding' => 4, 'next_value' => 1, 'is_active' => true,
    ]);

    return [$company, $supplier, $product, $tax, $warehouse, $location];
}

function goodsReceipt92Order(
    Company $company,
    Account $supplier,
    Product $product,
    Tax $tax,
    Warehouse $warehouse,
    WarehouseLocation $location,
    string $quantity,
): PurchaseOrder {
    $totals = DB::selectOne(
        'SELECT CAST(CAST(? AS numeric) * 100 AS numeric(20,6))::text AS base, '
        .'CAST(CAST(? AS numeric) * 20 AS numeric(20,6))::text AS tax, '
        .'CAST(CAST(? AS numeric) * 120 AS numeric(20,6))::text AS gross',
        [$quantity, $quantity, $quantity],
    );
    if ($totals === null) {
        throw new RuntimeException('Purchase order fixture totals could not be calculated.');
    }
    $base = (string) $totals->base;
    $taxTotal = (string) $totals->tax;
    $gross = (string) $totals->gross;

    $order = PurchaseOrder::query()->create([
        'company_id' => $company->getKey(),
        'account_id' => $supplier->getKey(),
        'number' => 'PO-'.$company->code,
        'series_code' => 'default',
        'sequence_value' => 1,
        'status' => PurchaseOrderStatus::Draft,
        'order_date' => '2026-08-27',
        'currency_code' => 'TRY',
        'document_discount_rate' => '0.000000',
        'base_net_total' => $base,
        'line_discount_total' => '0.000000',
        'document_discount_total' => '0.000000',
        'net_total' => $base,
        'tax_total' => $taxTotal,
        'gross_total' => $gross,
        'note' => null,
    ]);

    $order->lines()->create([
        'company_id' => $company->getKey(),
        'logical_line_key' => (string) Str::uuid(),
        'position' => 1,
        'product_id' => $product->getKey(),
        'warehouse_id' => $warehouse->getKey(),
        'location_id' => $location->getKey(),
        'product_code' => $product->code,
        'product_name' => $product->name,
        'description' => 'Satınalma satırı',
        'quantity' => $quantity,
        'price_basis' => PriceBasis::Net,
        'unit_price' => '100.000000',
        'line_discount_rate' => '0.000000',
        'tax_id' => $tax->getKey(),
        'tax_code' => $tax->code,
        'tax_rate' => '20.000000',
        'tax_is_zeroed' => false,
        'tax_zero_reason_id' => null,
        'tax_zero_reason_code' => null,
        'base_net' => $base,
        'line_discount_net' => '0.000000',
        'document_discount_net' => '0.000000',
        'net_total' => $base,
        'tax_total' => $taxTotal,
        'gross_total' => $gross,
    ]);

    $opener = \App\Modules\Core\Models\User::query()->create([
        'name' => 'Purchase Order Fixture Opener',
        'email' => strtolower((string) $company->code).'-po-opener-'.$order->getKey().'@fixture.test',
        'password' => 'not-used-in-test',
        'status' => 'active',
    ]);
    app(\App\Modules\PurchaseOrders\Actions\PurchaseOrderLifecycle::class)->open(
        (int) $company->getKey(),
        (int) $order->getKey(),
        (int) $opener->getKey(),
    );

    return $order->refresh()->load('lines.progress');
}

/** @return array<string, mixed> */
function goodsReceipt92Payload(
    PurchaseOrder $order,
    PurchaseOrderLine $line,
    Warehouse $warehouse,
    WarehouseLocation $location,
    string $received,
    string $accepted,
    string $pending,
    string $rejected,
): array {
    return [
        'series_code' => 'default',
        'purchase_order_id' => $order->getKey(),
        'receipt_date' => '2026-08-27',
        'note' => null,
        'lines' => [[
            'purchase_order_line_id' => $line->getKey(),
            'warehouse_id' => $warehouse->getKey(),
            'location_id' => $location->getKey(),
            'received_quantity' => $received,
            'accepted_quantity' => $accepted,
            'pending_quantity' => $pending,
            'rejected_quantity' => $rejected,
            'note' => null,
        ]],
    ];
}

/** @param list<PermissionKey> $permissions */
function goodsReceipt92Actor(Company $company, array $permissions, string $suffix): User
{
    $user = User::query()->create([
        'name' => 'Goods Receipt '.$suffix,
        'email' => strtolower((string) $company->code).'-'.$suffix.'@goods-receipt.test',
        'password' => 'correct-password',
        'status' => UserStatus::Active,
    ]);
    $membership = CompanyMembership::query()->create([
        'company_id' => $company->getKey(), 'user_id' => $user->getKey(), 'is_active' => true, 'joined_at' => now(),
    ]);
    $role = Role::query()->create([
        'company_id' => $company->getKey(), 'code' => 'goods-receipt-'.$suffix, 'name' => 'Goods Receipt '.$suffix, 'is_active' => true,
    ]);
    foreach ($permissions as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}
