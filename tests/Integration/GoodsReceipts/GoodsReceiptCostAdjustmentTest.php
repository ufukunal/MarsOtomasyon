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
use App\Modules\GoodsReceipts\Models\GoodsReceipt;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Ledger\PostStockMovementData;
use App\Modules\Inventory\Ledger\StockMovementPoster;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\PurchaseOrders\Actions\PurchaseOrderLifecycle;
use App\Modules\PurchaseOrders\Enums\PurchaseOrderStatus;
use App\Modules\PurchaseOrders\Models\PurchaseOrder;
use App\Modules\Quotes\Pricing\PriceBasis;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('splits late landed cost between on-hand and consumed quantity without a second receipt', function (): void {
    [$company, $supplier, $product, $tax, $warehouse, $location, $manager] = goodsReceiptCost96Fixture('GRC96-A');
    $order = goodsReceiptCost96Order($company, $supplier, $product, $tax, $warehouse, $location, '5');
    $line = $order->lines()->firstOrFail();

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('goods-receipts.store'), [
            'series_code' => 'default',
            'purchase_order_id' => $order->getKey(),
            'receipt_date' => '2026-08-27',
            'note' => null,
            'lines' => [[
                'purchase_order_line_id' => $line->getKey(),
                'warehouse_id' => $warehouse->getKey(),
                'location_id' => $location->getKey(),
                'received_quantity' => '5',
                'accepted_quantity' => '5',
                'pending_quantity' => '0',
                'rejected_quantity' => '0',
                'note' => null,
            ]],
        ])->assertRedirect();

    $receipt = GoodsReceipt::query()->firstOrFail();
    $receiptLine = $receipt->lines()->firstOrFail();
    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('goods-receipts.finalize', $receipt->getKey()))
        ->assertRedirect();

    DB::transaction(function () use ($company, $product, $warehouse, $location): void {
        app(StockMovementPoster::class)->post(new PostStockMovementData(
            sourceEffect: new SourceEffectIdentity((int) $company->getKey(), 'test_consumption', '1', 'stock.out'),
            productId: (int) $product->getKey(),
            warehouseId: (int) $warehouse->getKey(),
            locationId: (int) $location->getKey(),
            movementType: StockMovementType::AdjustmentOut,
            quantity: '2',
            note: 'M9.6 consumed quantity fixture',
        ));
    });

    $movementCountBefore = DB::table('stock_movements')->count();

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('goods-receipts.cost-adjustments.store', $receipt->getKey()), [
            'goods_receipt_line_id' => $receiptLine->getKey(),
            'reference' => 'FREIGHT-001',
            'total_value_delta' => '50',
            'note' => 'Geç gelen navlun farkı',
        ])->assertRedirect(route('goods-receipts.show', $receipt->getKey()));

    $adjustment = DB::table('goods_receipt_cost_adjustments')->first();
    $balance = DB::table('stock_balances')
        ->where('company_id', $company->getKey())
        ->where('product_id', $product->getKey())
        ->where('warehouse_id', $warehouse->getKey())
        ->where('location_id', $location->getKey())
        ->first();

    expect($adjustment)->not->toBeNull()
        ->and((string) $adjustment->eligible_quantity)->toBe('5.000000')
        ->and((string) $adjustment->on_hand_quantity_basis)->toBe('3.000000')
        ->and((string) $adjustment->consumed_quantity_basis)->toBe('2.000000')
        ->and((string) $adjustment->inventory_value_delta)->toBe('30.000000')
        ->and((string) $adjustment->consumed_cost_delta)->toBe('20.000000')
        ->and((string) $balance->quantity)->toBe('3.000000')
        ->and((string) $balance->inventory_value)->toBe('330.000000')
        ->and((string) $balance->average_unit_cost)->toBe('110.000000')
        ->and(DB::table('stock_movements')->count())->toBe($movementCountBefore);

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post(route('goods-receipts.cost-adjustments.store', $receipt->getKey()), [
            'goods_receipt_line_id' => $receiptLine->getKey(),
            'reference' => 'FREIGHT-001',
            'total_value_delta' => '50',
            'note' => 'Geç gelen navlun farkı',
        ])->assertRedirect();

    expect(DB::table('goods_receipt_cost_adjustments')->count())->toBe(1)
        ->and((string) DB::table('stock_balances')->where('company_id', $company->getKey())->value('inventory_value'))->toBe('330.000000')
        ->and(DB::table('stock_movements')->count())->toBe($movementCountBefore);
});

/** @return array{Company,Account,Product,Tax,Warehouse,WarehouseLocation,User} */
function goodsReceiptCost96Fixture(string $code): array
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

    $user = User::query()->create([
        'name' => 'Cost Manager', 'email' => strtolower($code).'@cost-adjustment.test',
        'password' => 'correct-password', 'status' => UserStatus::Active,
    ]);
    $membership = CompanyMembership::query()->create([
        'company_id' => $company->getKey(), 'user_id' => $user->getKey(), 'is_active' => true, 'joined_at' => now(),
    ]);
    $role = Role::query()->create([
        'company_id' => $company->getKey(), 'code' => 'cost-manager', 'name' => 'Cost Manager', 'is_active' => true,
    ]);
    foreach ([PermissionKey::GoodsReceiptView, PermissionKey::GoodsReceiptManage] as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return [$company, $supplier, $product, $tax, $warehouse, $location, $user];
}

function goodsReceiptCost96Order(
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

    $order = PurchaseOrder::query()->create([
        'company_id' => $company->getKey(), 'account_id' => $supplier->getKey(), 'number' => 'PO-'.$company->code,
        'series_code' => 'default', 'sequence_value' => 1, 'status' => PurchaseOrderStatus::Draft,
        'order_date' => '2026-08-27', 'currency_code' => 'TRY', 'document_discount_rate' => '0.000000',
        'base_net_total' => (string) $totals->base, 'line_discount_total' => '0.000000', 'document_discount_total' => '0.000000',
        'net_total' => (string) $totals->base, 'tax_total' => (string) $totals->tax, 'gross_total' => (string) $totals->gross, 'note' => null,
    ]);
    $order->lines()->create([
        'company_id' => $company->getKey(), 'logical_line_key' => (string) Str::uuid(), 'position' => 1,
        'product_id' => $product->getKey(), 'warehouse_id' => $warehouse->getKey(), 'location_id' => $location->getKey(),
        'product_code' => $product->code, 'product_name' => $product->name, 'description' => 'Satınalma satırı',
        'quantity' => $quantity, 'price_basis' => PriceBasis::Net, 'unit_price' => '100.000000',
        'line_discount_rate' => '0.000000', 'tax_id' => $tax->getKey(), 'tax_code' => $tax->code,
        'tax_rate' => '20.000000', 'tax_is_zeroed' => false, 'tax_zero_reason_id' => null, 'tax_zero_reason_code' => null,
        'base_net' => (string) $totals->base, 'line_discount_net' => '0.000000', 'document_discount_net' => '0.000000',
        'net_total' => (string) $totals->base, 'tax_total' => (string) $totals->tax, 'gross_total' => (string) $totals->gross,
    ]);

    $opener = User::query()->create([
        'name' => 'Purchase Order Fixture Opener',
        'email' => strtolower((string) $company->code).'-po-opener-'.$order->getKey().'@fixture.test',
        'password' => 'not-used-in-test',
        'status' => 'active',
    ]);
    app(PurchaseOrderLifecycle::class)->open(
        (int) $company->getKey(),
        (int) $order->getKey(),
        (int) $opener->getKey(),
    );

    return $order->refresh()->load('lines.progress');
}
