<?php

use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Authorization\AssignRoleToMembership;
use App\Modules\Core\Authorization\GrantPermissionToRole;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\Tax;
use App\Modules\Core\Models\User;
use App\Modules\GoodsReceipts\Actions\FinalizeGoodsReceipt;
use App\Modules\GoodsReceipts\Models\GoodsReceipt;
use App\Modules\Imports\Actions\ImportOperations;
use App\Modules\Imports\Models\ImportLandedCostBatch;
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

it('reconciles import receipt and posts final landed cost exactly once through goods receipt cost authority', function (): void {
    [$company, $supplier, $product, $tax, $warehouse, $location, $manager] = m16Fixture('M16-A');
    $order = m16PurchaseOrder($company, $supplier, $product, $tax, $warehouse, $location, $manager, '5');
    $orderLine = $order->lines()->firstOrFail();

    $receipt = GoodsReceipt::query()->create([
        'company_id' => $company->getKey(),
        'purchase_order_id' => $order->getKey(),
        'account_id' => $supplier->getKey(),
        'number' => 'GR-M16-A',
        'series_code' => 'default',
        'sequence_value' => 1,
        'status' => 'draft',
        'receipt_date' => '2026-08-30',
        'note' => 'M16 import receipt',
        'finalized_at' => null,
    ]);
    $receiptLine = $receipt->lines()->create([
        'company_id' => $company->getKey(),
        'purchase_order_id' => $order->getKey(),
        'purchase_order_line_id' => $orderLine->getKey(),
        'position' => 1,
        'product_id' => $product->getKey(),
        'warehouse_id' => $warehouse->getKey(),
        'location_id' => $location->getKey(),
        'product_code' => $product->code,
        'product_name' => $product->name,
        'received_quantity' => '5',
        'accepted_quantity' => '5',
        'pending_quantity' => '0',
        'rejected_quantity' => '0',
        'provisional_unit_cost' => '100',
        'note' => null,
    ]);

    $this->actingAs($manager);
    app(ActiveCompanyContext::class)->set($company);
    app(FinalizeGoodsReceipt::class)->handle((int) $receipt->getKey());

    $stockMovementCount = DB::table('stock_movements')->count();
    $balanceBefore = DB::table('stock_balances')->where('company_id', $company->getKey())->where('product_id', $product->getKey())->first();
    expect($balanceBefore)->not->toBeNull()
        ->and((string) $balanceBefore->quantity)->toBe('5.000000')
        ->and((string) $balanceBefore->inventory_value)->toBe('500.000000');

    $imports = app(ImportOperations::class);
    $file = $imports->createFile('IMP-2026-001', 'TRY', (int) $supplier->getKey(), originCountry: 'CN', destinationPort: 'Ambarlı');
    $container = $imports->addContainer((int) $file->getKey(), 'MSCU1234567', maxWeightKg: '28000', maxVolumeM3: '67');
    $item = $imports->addItem((int) $file->getKey(), (int) $product->getKey(), '5', (int) $container->getKey(), 'PKG-01', 'COMP-A', 5, '500', '450', '4.5', 'C1/A01', true);
    $imports->markInTransit((int) $file->getKey());
    $imports->markArrived((int) $file->getKey(), '2026-08-30');
    $imports->linkReceiptLine((int) $file->getKey(), (int) $item->getKey(), (int) $receiptLine->getKey());
    $imports->recordExpense((int) $file->getKey(), 'FREIGHT', 'Navlun', '50', 'TRY', 'line_value', true);

    $batch = $imports->postLandedCost((int) $file->getKey(), 'LC-001', 'line_value');
    $replayed = $imports->postLandedCost((int) $file->getKey(), 'LC-001', 'line_value');
    $completed = $imports->complete((int) $file->getKey());

    $balanceAfter = DB::table('stock_balances')->where('company_id', $company->getKey())->where('product_id', $product->getKey())->first();
    expect($replayed->getKey())->toBe($batch->getKey())
        ->and(ImportLandedCostBatch::query()->count())->toBe(1)
        ->and(DB::table('import_landed_cost_allocations')->count())->toBe(1)
        ->and(DB::table('goods_receipt_cost_adjustments')->count())->toBe(1)
        ->and((string) DB::table('import_landed_cost_allocations')->value('allocated_amount'))->toBe('50.000000')
        ->and(DB::table('stock_movements')->count())->toBe($stockMovementCount)
        ->and($balanceAfter)->not->toBeNull()
        ->and((string) $balanceAfter->quantity)->toBe('5.000000')
        ->and((string) $balanceAfter->inventory_value)->toBe('550.000000')
        ->and((string) $balanceAfter->average_unit_cost)->toBe('110.000000')
        ->and($completed->status)->toBe('completed')
        ->and($imports->loadingSummary((int) $file->getKey()))->toMatchArray(['item_count' => 1, 'package_count' => 5, 'gross_weight_kg' => '500.000000', 'volume_m3' => '4.500000'])
        ->and($imports->subcontractCollectionRows((int) $file->getKey()))->toHaveCount(1);
});

it('keeps provisional expenses out of carrying value and enforces import RBAC', function (): void {
    [$company, $supplier, $product, , , , $manager] = m16Fixture('M16-B');
    $this->actingAs($manager);
    app(ActiveCompanyContext::class)->set($company);

    $imports = app(ImportOperations::class);
    $file = $imports->createFile('IMP-2026-002', 'TRY', (int) $supplier->getKey());
    $imports->addItem((int) $file->getKey(), (int) $product->getKey(), '1');
    $imports->recordExpense((int) $file->getKey(), 'DUTY', 'Gümrük', '25', 'TRY', 'line_value', false);

    expect(DB::table('stock_balances')->where('company_id', $company->getKey())->count())->toBe(0)
        ->and(DB::table('goods_receipt_cost_adjustments')->count())->toBe(0);

    $viewer = User::query()->create(['name' => 'No Import Permission', 'email' => 'no-import-'.strtolower($company->code).'@test.local', 'password' => 'password', 'status' => UserStatus::Active]);
    CompanyMembership::query()->create(['company_id' => $company->getKey(), 'user_id' => $viewer->getKey(), 'is_active' => true, 'joined_at' => now()]);

    $this->actingAs($viewer)->withSession(['active_company_id' => $company->getKey()])->get(route('import.index'))->assertForbidden();
    $this->actingAs($manager)->withSession(['active_company_id' => $company->getKey()])->get(route('import.index'))->assertOk();
});

/** @return array{Company,Account,Product,Tax,Warehouse,WarehouseLocation,User} */
function m16Fixture(string $code): array
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
    $manager = User::query()->create(['name' => 'Import Manager', 'email' => strtolower($code).'@m16.test', 'password' => 'password', 'status' => UserStatus::Active]);
    $membership = CompanyMembership::query()->create(['company_id' => $company->getKey(), 'user_id' => $manager->getKey(), 'is_active' => true, 'joined_at' => now()]);
    $role = Role::query()->create(['company_id' => $company->getKey(), 'code' => 'import-manager', 'name' => 'Import Manager', 'is_active' => true]);
    foreach ([PermissionKey::ImportView, PermissionKey::ImportManage, PermissionKey::GoodsReceiptView, PermissionKey::GoodsReceiptManage] as $permission) {
        app(GrantPermissionToRole::class)->handle($role, $permission);
    }
    app(AssignRoleToMembership::class)->handle($membership, $role);

    return [$company, $supplier, $product, $tax, $warehouse, $location, $manager];
}

function m16PurchaseOrder(Company $company, Account $supplier, Product $product, Tax $tax, Warehouse $warehouse, WarehouseLocation $location, User $manager, string $quantity): PurchaseOrder
{
    $totals = DB::selectOne('SELECT CAST(CAST(? AS numeric) * 100 AS numeric(20,6))::text AS base, CAST(CAST(? AS numeric) * 20 AS numeric(20,6))::text AS tax, CAST(CAST(? AS numeric) * 120 AS numeric(20,6))::text AS gross', [$quantity, $quantity, $quantity]);
    if ($totals === null) {
        throw new RuntimeException('M16 purchase order totals could not be calculated.');
    }
    $order = PurchaseOrder::query()->create([
        'company_id' => $company->getKey(), 'account_id' => $supplier->getKey(), 'number' => 'PO-'.$company->code,
        'series_code' => 'default', 'sequence_value' => 1, 'status' => PurchaseOrderStatus::Draft,
        'order_date' => '2026-08-30', 'currency_code' => 'TRY', 'document_discount_rate' => '0.000000',
        'base_net_total' => (string) $totals->base, 'line_discount_total' => '0.000000', 'document_discount_total' => '0.000000',
        'net_total' => (string) $totals->base, 'tax_total' => (string) $totals->tax, 'gross_total' => (string) $totals->gross, 'note' => null,
    ]);
    $order->lines()->create([
        'company_id' => $company->getKey(), 'logical_line_key' => (string) Str::uuid(), 'position' => 1,
        'product_id' => $product->getKey(), 'warehouse_id' => $warehouse->getKey(), 'location_id' => $location->getKey(),
        'product_code' => $product->code, 'product_name' => $product->name, 'description' => 'M16 ithalat satırı',
        'quantity' => $quantity, 'price_basis' => PriceBasis::Net, 'unit_price' => '100.000000', 'line_discount_rate' => '0.000000',
        'tax_id' => $tax->getKey(), 'tax_code' => $tax->code, 'tax_rate' => '20.000000', 'tax_is_zeroed' => false,
        'tax_zero_reason_id' => null, 'tax_zero_reason_code' => null, 'base_net' => (string) $totals->base,
        'line_discount_net' => '0.000000', 'document_discount_net' => '0.000000', 'net_total' => (string) $totals->base,
        'tax_total' => (string) $totals->tax, 'gross_total' => (string) $totals->gross,
    ]);
    app(PurchaseOrderLifecycle::class)->open((int) $company->getKey(), (int) $order->getKey(), (int) $manager->getKey());

    return $order->refresh()->load('lines.progress');
}
