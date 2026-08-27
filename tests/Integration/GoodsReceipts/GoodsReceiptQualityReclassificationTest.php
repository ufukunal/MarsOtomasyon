<?php

use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Authorization\AssignRoleToMembership;
use App\Modules\Core\Authorization\GrantPermissionToRole;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\Tax;
use App\Modules\Core\Models\User;
use App\Modules\GoodsReceipts\Enums\GoodsReceiptStatus;
use App\Modules\GoodsReceipts\Models\GoodsReceipt;
use App\Modules\GoodsReceipts\Models\GoodsReceiptLine;
use App\Modules\GoodsReceipts\Models\GoodsReceiptQualityEffect;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\PurchaseOrders\Enums\PurchaseOrderStatus;
use App\Modules\PurchaseOrders\Models\PurchaseOrder;
use App\Modules\Quotes\Pricing\PriceBasis;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDOException;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('moves pending custody to accepted with exact stock progress and audit effects', function (): void {
    [$company, $product, $receipt, $line, $actor] = goodsReceipt93FinalizedPendingFixture('GR93-A', '5');

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/goods-receipts/'.$receipt->getKey().'/quality', [
            'goods_receipt_line_id' => $line->getKey(),
            'disposition' => 'accepted',
            'quantity' => '2',
            'note' => 'Kalite kontrolden geçti',
        ])
        ->assertRedirect('/goods-receipts/'.$receipt->getKey());

    $effect = GoodsReceiptQualityEffect::query()->firstOrFail();
    $quality = DB::table('goods_receipt_line_quality')->where('goods_receipt_line_id', $line->getKey())->first();
    $movement = DB::table('stock_movements')
        ->where('source_type', 'goods_receipt_quality_effect')
        ->where('source_id', (string) $effect->getKey())
        ->where('effect_type', 'stock.in')
        ->first();
    $progress = DB::table('purchase_order_line_progress_effects')
        ->where('source_type', 'goods_receipt_quality_effect')
        ->where('source_id', (string) $effect->getKey())
        ->where('effect_type', 'progress.receive')
        ->first();
    $balance = DB::table('stock_balances')->where('product_id', $product->getKey())->first();
    $audit = DB::table('audit_entries')
        ->where('action', 'goods_receipts.quality.reclassified')
        ->where('target_type', 'goods_receipt')
        ->where('target_id', (string) $receipt->getKey())
        ->first();

    expect((string) $quality->accepted_quantity)->toBe('2.000000')
        ->and((string) $quality->pending_quantity)->toBe('3.000000')
        ->and((string) $quality->rejected_quantity)->toBe('0.000000')
        ->and($movement)->not->toBeNull()
        ->and((string) $movement->movement_type)->toBe('goods_receipt_in')
        ->and((string) $movement->quantity_delta)->toBe('2.000000')
        ->and((string) $movement->unit_cost)->toBe('100.000000')
        ->and($progress)->not->toBeNull()
        ->and((string) $progress->progress_type)->toBe('received')
        ->and((string) $progress->quantity_delta)->toBe('2.000000')
        ->and((string) $balance->quantity)->toBe('2.000000')
        ->and($audit)->not->toBeNull()
        ->and((string) data_get(json_decode((string) $audit->metadata, true), 'quality_effect_id'))->toBe((string) $effect->getKey());
});

it('moves pending custody to rejected without stock or purchase progress', function (): void {
    [$company, , $receipt, $line, $actor] = goodsReceipt93FinalizedPendingFixture('GR93-B', '5');

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/goods-receipts/'.$receipt->getKey().'/quality', [
            'goods_receipt_line_id' => $line->getKey(),
            'disposition' => 'rejected',
            'quantity' => '1.5',
            'note' => 'Hasarlı ürün',
        ])
        ->assertRedirect('/goods-receipts/'.$receipt->getKey());

    $effect = GoodsReceiptQualityEffect::query()->firstOrFail();
    $quality = DB::table('goods_receipt_line_quality')->where('goods_receipt_line_id', $line->getKey())->first();

    expect((string) $quality->accepted_quantity)->toBe('0.000000')
        ->and((string) $quality->pending_quantity)->toBe('3.500000')
        ->and((string) $quality->rejected_quantity)->toBe('1.500000')
        ->and(DB::table('stock_movements')->where('source_type', 'goods_receipt_quality_effect')->count())->toBe(0)
        ->and(DB::table('purchase_order_line_progress_effects')->where('source_type', 'goods_receipt_quality_effect')->count())->toBe(0)
        ->and(DB::table('audit_entries')->where('metadata->quality_effect_id', $effect->getKey())->count())->toBe(1);
});

it('blocks reclassification beyond remaining pending custody and rolls the transaction back', function (): void {
    [$company, , $receipt, $line, $actor] = goodsReceipt93FinalizedPendingFixture('GR93-C', '3');

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/goods-receipts/'.$receipt->getKey().'/quality', [
            'goods_receipt_line_id' => $line->getKey(),
            'disposition' => 'accepted',
            'quantity' => '2',
        ])
        ->assertRedirect();

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/goods-receipts/'.$receipt->getKey().'/quality', [
            'goods_receipt_line_id' => $line->getKey(),
            'disposition' => 'rejected',
            'quantity' => '2',
        ])
        ->assertSessionHasErrors('quantity');

    $quality = DB::table('goods_receipt_line_quality')->where('goods_receipt_line_id', $line->getKey())->first();

    expect(GoodsReceiptQualityEffect::query()->count())->toBe(1)
        ->and(DB::table('stock_movements')->where('source_type', 'goods_receipt_quality_effect')->count())->toBe(1)
        ->and(DB::table('purchase_order_line_progress_effects')->where('source_type', 'goods_receipt_quality_effect')->count())->toBe(1)
        ->and(DB::table('audit_entries')->where('action', 'goods_receipts.quality.reclassified')->count())->toBe(1)
        ->and((string) $quality->accepted_quantity)->toBe('2.000000')
        ->and((string) $quality->pending_quantity)->toBe('1.000000')
        ->and((string) $quality->rejected_quantity)->toBe('0.000000');
});

it('rejects raw accepted quality effects without exact authority effects at commit', function (): void {
    [$company, , $receipt, $line, $actor] = goodsReceipt93FinalizedPendingFixture('GR93-D', '2');

    expect(fn () => DB::transaction(function () use ($company, $receipt, $line, $actor): void {
        DB::table('goods_receipt_quality_effects')->insert([
            'company_id' => $company->getKey(),
            'goods_receipt_id' => $receipt->getKey(),
            'goods_receipt_line_id' => $line->getKey(),
            'disposition' => 'accepted',
            'quantity' => '1.000000',
            'note' => null,
            'created_by_user_id' => $actor->getKey(),
            'occurred_at' => now(),
            'created_at' => now(),
        ]);
    }))->toThrow(PDOException::class);

    expect(GoodsReceiptQualityEffect::query()->count())->toBe(0)
        ->and(DB::table('stock_movements')->where('source_type', 'goods_receipt_quality_effect')->count())->toBe(0)
        ->and(DB::table('purchase_order_line_progress_effects')->where('source_type', 'goods_receipt_quality_effect')->count())->toBe(0);
});

it('keeps quality effects append only at the PostgreSQL boundary', function (): void {
    [$company, , $receipt, $line, $actor] = goodsReceipt93FinalizedPendingFixture('GR93-E', '2');

    $this->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/goods-receipts/'.$receipt->getKey().'/quality', [
            'goods_receipt_line_id' => $line->getKey(),
            'disposition' => 'rejected',
            'quantity' => '1',
        ])
        ->assertRedirect();

    $effect = GoodsReceiptQualityEffect::query()->firstOrFail();

    expect(fn () => DB::table('goods_receipt_quality_effects')->where('id', $effect->getKey())->update(['quantity' => '0.500000']))
        ->toThrow(QueryException::class);
    expect(fn () => DB::table('goods_receipt_quality_effects')->where('id', $effect->getKey())->delete())
        ->toThrow(QueryException::class);
});

/** @return array{Company, Product, GoodsReceipt, GoodsReceiptLine, User} */
function goodsReceipt93FinalizedPendingFixture(string $code, string $pending): array
{
    $company = Company::query()->create(['code' => $code, 'name' => 'Company '.$code]);
    $supplier = Account::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'SUP',
        'type' => AccountType::Supplier,
        'status' => AccountStatus::Active,
        'legal_name' => 'Tedarikçi '.$code,
        'trade_name' => null,
        'tax_identity_type' => TaxIdentityType::None,
        'tax_number' => null,
        'tax_office' => null,
        'book_currency_code' => 'TRY',
        'due_days' => 0,
        'discount_rate' => '0.000000',
        'risk_limit' => '0.000000',
    ]);
    $category = Category::query()->create(['company_id' => $company->getKey(), 'code' => 'CAT', 'name' => 'Kategori', 'is_active' => true]);
    $unit = Unit::query()->create(['company_id' => $company->getKey(), 'code' => 'ADET', 'name' => 'Adet', 'is_active' => true]);
    $tax = Tax::query()->create(['company_id' => $company->getKey(), 'code' => 'KDV20', 'name' => 'KDV %20', 'rate' => '20.000000', 'is_active' => true]);
    $product = Product::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'SKU',
        'status' => ProductStatus::Active,
        'name' => 'Ürün '.$code,
        'category_id' => $category->getKey(),
        'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(),
        'sale_price_net' => '120.000000',
        'purchase_price_net' => '100.000000',
    ]);
    $warehouse = Warehouse::query()->create(['company_id' => $company->getKey(), 'code' => 'WH', 'name' => 'Ana Depo', 'is_active' => true]);
    $location = WarehouseLocation::query()->create([
        'company_id' => $company->getKey(),
        'warehouse_id' => $warehouse->getKey(),
        'code' => 'A1',
        'name' => 'A1',
        'is_active' => true,
    ]);

    $order = goodsReceipt93Order($company, $supplier, $product, $tax, $warehouse, $location, $pending);
    $orderLine = $order->lines()->firstOrFail();
    $receipt = GoodsReceipt::query()->create([
        'company_id' => $company->getKey(),
        'purchase_order_id' => $order->getKey(),
        'account_id' => $supplier->getKey(),
        'number' => 'GR-'.$code,
        'series_code' => 'default',
        'sequence_value' => 1,
        'status' => GoodsReceiptStatus::Draft,
        'receipt_date' => '2026-08-27',
        'note' => null,
        'finalized_at' => null,
    ]);
    $line = $receipt->lines()->create([
        'company_id' => $company->getKey(),
        'purchase_order_id' => $order->getKey(),
        'purchase_order_line_id' => $orderLine->getKey(),
        'position' => 1,
        'product_id' => $product->getKey(),
        'warehouse_id' => $warehouse->getKey(),
        'location_id' => $location->getKey(),
        'product_code' => $product->code,
        'product_name' => $product->name,
        'received_quantity' => $pending,
        'accepted_quantity' => '0.000000',
        'pending_quantity' => $pending,
        'rejected_quantity' => '0.000000',
        'provisional_unit_cost' => '100.000000',
        'note' => null,
    ]);
    $actor = goodsReceipt93Actor($company, 'manager-'.$code);

    test()->actingAs($actor)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post('/goods-receipts/'.$receipt->getKey().'/finalize')
        ->assertRedirect('/goods-receipts/'.$receipt->getKey());

    return [$company, $product, $receipt->refresh(), $line, $actor];
}

function goodsReceipt93Order(
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
        'company_id' => $company->getKey(),
        'account_id' => $supplier->getKey(),
        'number' => 'PO-'.$company->code,
        'series_code' => 'default',
        'sequence_value' => 1,
        'status' => PurchaseOrderStatus::Draft,
        'order_date' => '2026-08-27',
        'currency_code' => 'TRY',
        'document_discount_rate' => '0.000000',
        'base_net_total' => (string) $totals->base,
        'line_discount_total' => '0.000000',
        'document_discount_total' => '0.000000',
        'net_total' => (string) $totals->base,
        'tax_total' => (string) $totals->tax,
        'gross_total' => (string) $totals->gross,
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
        'base_net' => (string) $totals->base,
        'line_discount_net' => '0.000000',
        'document_discount_net' => '0.000000',
        'net_total' => (string) $totals->base,
        'tax_total' => (string) $totals->tax,
        'gross_total' => (string) $totals->gross,
    ]);

    return $order->load('lines.progress');
}

function goodsReceipt93Actor(Company $company, string $suffix): User
{
    $user = User::query()->create([
        'name' => 'Goods Receipt Quality '.$suffix,
        'email' => strtolower((string) $company->code).'-'.$suffix.'@goods-receipt-quality.test',
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
        'code' => 'goods-receipt-quality-'.$suffix,
        'name' => 'Goods Receipt Quality '.$suffix,
        'is_active' => true,
    ]);
    foreach ([PermissionKey::GoodsReceiptView, PermissionKey::GoodsReceiptManage, PermissionKey::PurchaseOrderView] as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return $user;
}
