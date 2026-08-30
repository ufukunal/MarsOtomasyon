<?php

use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Tax;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Ledger\PostStockMovementData;
use App\Modules\Inventory\Ledger\StockMovementPoster;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\Subcontract\Actions\SubcontractOperations;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('preserves subcontract custody carrying value through loss, partial consumption and finished-goods receipt exactly once', function (): void {
    [$company, $supplier, $material, $output, $warehouse, $location] = m15Fixture('M15-A');
    $ops = app(SubcontractOperations::class);
    $order = $ops->createOrder((int) $company->getKey(), (int) $supplier->getKey(), (int) $output->getKey(), 'FASON-001', '4', (int) $warehouse->getKey(), (int) $location->getKey(), [['product_id' => (int) $material->getKey(), 'quantity' => '6']]);
    $sent = $ops->sendMaterials((int) $company->getKey(), (int) $order->getKey());
    $ops->sendMaterials((int) $company->getKey(), (int) $order->getKey());

    expect((string) $sent->sent_value)->toBe('30.000000')
        ->and(DB::table('stock_movements')->where('movement_type', StockMovementType::SubcontractSendOut->value)->count())->toBe(1)
        ->and(m15Balance($material, $warehouse, $location)->quantity)->toBe('4.000000');

    $loss = $ops->recordLoss((int) $company->getKey(), (int) $order->getKey(), 'loss-1', (int) $material->getKey(), '1', 'fire');
    $lossReplay = $ops->recordLoss((int) $company->getKey(), (int) $order->getKey(), 'loss-1', (int) $material->getKey(), '1', 'fire');
    expect((string) $loss->carrying_value)->toBe('5.000000')->and($lossReplay->getKey())->toBe($loss->getKey());

    $receipt = $ops->receiveOutput((int) $company->getKey(), (int) $order->getKey(), 'receipt-1', '4', [['product_id' => (int) $material->getKey(), 'quantity' => '5']]);
    $receiptReplay = $ops->receiveOutput((int) $company->getKey(), (int) $order->getKey(), 'receipt-1', '4', [['product_id' => (int) $material->getKey(), 'quantity' => '5']]);
    $completed = $ops->complete((int) $company->getKey(), (int) $order->getKey());

    expect((string) $receipt->carrying_value)->toBe('25.000000')
        ->and($receiptReplay->getKey())->toBe($receipt->getKey())
        ->and($completed->status)->toBe('completed')
        ->and(DB::table('stock_movements')->where('movement_type', StockMovementType::SubcontractReceiptIn->value)->count())->toBe(1)
        ->and(m15Balance($output, $warehouse, $location)->quantity)->toBe('4.000000')
        ->and(m15Balance($output, $warehouse, $location)->inventory_value)->toBe('25.000000')
        ->and(m15Balance($output, $warehouse, $location)->average_unit_cost)->toBe('6.250000');

    $materialRow = DB::table('subcontract_order_materials')->where('subcontract_order_id', $order->getKey())->first();
    expect((string) $materialRow->sent_quantity)->toBe('6.000000')
        ->and((string) $materialRow->consumed_quantity)->toBe('5.000000')
        ->and((string) $materialRow->loss_quantity)->toBe('1.000000')
        ->and((string) $materialRow->sent_value)->toBe('30.000000')
        ->and((string) $materialRow->consumed_value)->toBe('25.000000')
        ->and((string) $materialRow->loss_value)->toBe('5.000000');
});

it('rolls back material send on insufficient physical stock and rejects receipt payload drift', function (): void {
    [$company, $supplier, $material, $output, $warehouse, $location] = m15Fixture('M15-B');
    $ops = app(SubcontractOperations::class);
    $tooLarge = $ops->createOrder((int) $company->getKey(), (int) $supplier->getKey(), (int) $output->getKey(), 'FASON-BIG', '1', (int) $warehouse->getKey(), (int) $location->getKey(), [['product_id' => (int) $material->getKey(), 'quantity' => '11']]);
    expect(fn () => $ops->sendMaterials((int) $company->getKey(), (int) $tooLarge->getKey()))->toThrow(ValidationException::class);
    expect(DB::table('stock_movements')->where('movement_type', StockMovementType::SubcontractSendOut->value)->count())->toBe(0);

    $order = $ops->createOrder((int) $company->getKey(), (int) $supplier->getKey(), (int) $output->getKey(), 'FASON-DRIFT', '2', (int) $warehouse->getKey(), (int) $location->getKey(), [['product_id' => (int) $material->getKey(), 'quantity' => '4']]);
    $ops->sendMaterials((int) $company->getKey(), (int) $order->getKey());
    $ops->receiveOutput((int) $company->getKey(), (int) $order->getKey(), 'same-receipt', '1', [['product_id' => (int) $material->getKey(), 'quantity' => '2']]);
    expect(fn () => $ops->receiveOutput((int) $company->getKey(), (int) $order->getKey(), 'same-receipt', '2', [['product_id' => (int) $material->getKey(), 'quantity' => '2']]))->toThrow(DomainException::class, 'farklı payload');
});

/** @return array{Company, Account, Product, Product, Warehouse, WarehouseLocation} */
function m15Fixture(string $code): array
{
    $company = Company::query()->create(['code' => $code, 'name' => 'Company '.$code]);
    $supplier = Account::query()->create(['company_id' => $company->getKey(), 'code' => 'SUP-'.$code, 'type' => AccountType::Supplier, 'status' => AccountStatus::Active, 'legal_name' => 'Fasoncu '.$code, 'trade_name' => null, 'tax_identity_type' => TaxIdentityType::None, 'tax_number' => null, 'tax_office' => null, 'book_currency_code' => 'TRY', 'due_days' => 0, 'discount_rate' => '0.000000', 'risk_limit' => '0.000000']);
    $category = Category::query()->create(['company_id' => $company->getKey(), 'code' => 'FASON', 'name' => 'Fason', 'is_active' => true]);
    $unit = Unit::query()->create(['company_id' => $company->getKey(), 'code' => 'ADET', 'name' => 'Adet', 'is_active' => true]);
    $tax = Tax::query()->create(['company_id' => $company->getKey(), 'code' => 'KDV20', 'name' => 'KDV %20', 'rate' => '20.000000', 'is_active' => true]);
    $material = Product::query()->create(['company_id' => $company->getKey(), 'code' => 'MAT-'.$code, 'status' => ProductStatus::Active, 'name' => 'Fason Malzeme '.$code, 'category_id' => $category->getKey(), 'unit_id' => $unit->getKey(), 'tax_id' => $tax->getKey(), 'sale_price_net' => '20.000000', 'purchase_price_net' => '5.000000']);
    $output = Product::query()->create(['company_id' => $company->getKey(), 'code' => 'OUT-'.$code, 'status' => ProductStatus::Active, 'name' => 'Fason Mamul '.$code, 'category_id' => $category->getKey(), 'unit_id' => $unit->getKey(), 'tax_id' => $tax->getKey(), 'sale_price_net' => '50.000000', 'purchase_price_net' => '10.000000']);
    $warehouse = Warehouse::query()->create(['company_id' => $company->getKey(), 'code' => 'MAIN', 'name' => 'Merkez', 'is_active' => true]);
    $location = WarehouseLocation::query()->create(['company_id' => $company->getKey(), 'warehouse_id' => $warehouse->getKey(), 'code' => 'FASON-01', 'name' => 'Fason Rafı', 'is_active' => true]);
    DB::transaction(fn () => app(StockMovementPoster::class)->post(new PostStockMovementData(sourceEffect: new SourceEffectIdentity((int) $company->getKey(), 'subcontract.fixture', $code, 'inventory.opening'), productId: (int) $material->getKey(), warehouseId: (int) $warehouse->getKey(), locationId: (int) $location->getKey(), movementType: StockMovementType::OpeningIn, quantity: '10', unitCost: '5')));

    return [$company, $supplier, $material, $output, $warehouse, $location];
}

function m15Balance(Product $product, Warehouse $warehouse, WarehouseLocation $location): StockBalance
{
    return StockBalance::query()->where('product_id', $product->getKey())->where('warehouse_id', $warehouse->getKey())->where('location_id', $location->getKey())->firstOrFail();
}
