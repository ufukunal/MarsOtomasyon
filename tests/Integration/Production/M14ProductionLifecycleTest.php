<?php

use App\Foundation\Identity\SourceEffectIdentity;
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
use App\Modules\Production\Actions\ProductionOperations;
use App\Modules\Production\Models\ProductionOrder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(DatabaseMigrations::class);

it('runs recipe to material issue, loss, finished-goods receipt and completion with carrying-cost allocation exactly once', function (): void {
    [$company, $materialA, $materialB, $finishedGood, $warehouse, $location] = m14Fixture('M14-A');
    $ops = app(ProductionOperations::class);

    $recipe = $ops->createRecipe(
        (int) $company->getKey(),
        (int) $finishedGood->getKey(),
        'REC-LAMP',
        'Lamba Reçetesi',
        '2',
        [
            ['product_id' => (int) $materialA->getKey(), 'quantity' => '3'],
            ['product_id' => (int) $materialB->getKey(), 'quantity' => '4'],
        ],
    );

    $order = $ops->createOrder(
        (int) $company->getKey(),
        (int) $recipe->getKey(),
        'PRD-0001',
        '4',
        (int) $warehouse->getKey(),
        (int) $location->getKey(),
    );

    $requirements = $order->materials->pluck('required_quantity', 'product_id')->map(fn ($value): string => (string) $value)->all();
    expect($requirements)->toBe([
        $materialA->getKey() => '6.000000',
        $materialB->getKey() => '8.000000',
    ]);

    $issued = $ops->issueMaterials((int) $company->getKey(), (int) $order->getKey());
    $issuedReplay = $ops->issueMaterials((int) $company->getKey(), (int) $order->getKey());

    expect($issued->status)->toBe('in_progress')
        ->and((string) $issued->material_cost)->toBe('46.000000')
        ->and($issuedReplay->getKey())->toBe($issued->getKey())
        ->and(DB::table('stock_movements')->where('movement_type', StockMovementType::ProductionMaterialOut->value)->count())->toBe(2);

    $loss = $ops->recordLoss(
        (int) $company->getKey(),
        (int) $order->getKey(),
        'loss-fire-0001',
        (int) $materialA->getKey(),
        '1',
        'fire',
        'Kesim firesi',
    );
    $lossReplay = $ops->recordLoss(
        (int) $company->getKey(),
        (int) $order->getKey(),
        'loss-fire-0001',
        (int) $materialA->getKey(),
        '1',
        'fire',
        'Kesim firesi',
    );

    expect((string) $loss->carrying_value)->toBe('5.000000')
        ->and($lossReplay->getKey())->toBe($loss->getKey())
        ->and(DB::table('production_losses')->count())->toBe(1)
        ->and(DB::table('stock_movements')->where('movement_type', StockMovementType::ProductionLossOut->value)->count())->toBe(1);

    $received = $ops->receiveOutput((int) $company->getKey(), (int) $order->getKey());
    $receivedReplay = $ops->receiveOutput((int) $company->getKey(), (int) $order->getKey());
    $completed = $ops->complete((int) $company->getKey(), (int) $order->getKey());
    $completedReplay = $ops->complete((int) $company->getKey(), (int) $order->getKey());

    expect($received->status)->toBe('received')
        ->and((string) $received->loss_cost)->toBe('5.000000')
        ->and((string) $received->output_quantity)->toBe('4.000000')
        ->and((string) $received->output_value)->toBe('51.000000')
        ->and((string) $received->output_unit_cost)->toBe('12.750000')
        ->and($receivedReplay->getKey())->toBe($received->getKey())
        ->and($completed->status)->toBe('completed')
        ->and($completedReplay->getKey())->toBe($completed->getKey())
        ->and(DB::table('stock_movements')->where('movement_type', StockMovementType::ProductionReceiptIn->value)->count())->toBe(1);

    expect(m14Balance($materialA, $warehouse, $location)->quantity)->toBe('3.000000')
        ->and(m14Balance($materialB, $warehouse, $location)->quantity)->toBe('12.000000')
        ->and(m14Balance($finishedGood, $warehouse, $location)->quantity)->toBe('4.000000')
        ->and(m14Balance($finishedGood, $warehouse, $location)->inventory_value)->toBe('51.000000')
        ->and(m14Balance($finishedGood, $warehouse, $location)->average_unit_cost)->toBe('12.750000');

    expect(DB::table('production_events')->where('production_order_id', $order->getKey())->count())->toBe(5);
    expect(fn () => DB::table('production_events')->where('production_order_id', $order->getKey())->update(['event_type' => 'tampered']))
        ->toThrow(QueryException::class);
    expect(fn () => ProductionOrder::query()->whereKey($order->getKey())->update(['note' => 'tampered']))
        ->toThrow(QueryException::class);
});

it('rolls back the complete material issue batch when physical stock is insufficient', function (): void {
    [$company, $materialA, $materialB, $finishedGood, $warehouse, $location] = m14Fixture('M14-B');
    $ops = app(ProductionOperations::class);

    $recipe = $ops->createRecipe(
        (int) $company->getKey(),
        (int) $finishedGood->getKey(),
        'REC-BIG',
        'Büyük Reçete',
        '1',
        [
            ['product_id' => (int) $materialA->getKey(), 'quantity' => '20'],
            ['product_id' => (int) $materialB->getKey(), 'quantity' => '1'],
        ],
    );
    $order = $ops->createOrder(
        (int) $company->getKey(),
        (int) $recipe->getKey(),
        'PRD-LOW-STOCK',
        '1',
        (int) $warehouse->getKey(),
        (int) $location->getKey(),
    );

    expect(fn () => $ops->issueMaterials((int) $company->getKey(), (int) $order->getKey()))
        ->toThrow(ValidationException::class);

    $order->refresh();
    expect($order->status)->toBe('draft')
        ->and($order->material_issued_at)->toBeNull()
        ->and(DB::table('stock_movements')->whereIn('movement_type', [
            StockMovementType::ProductionMaterialOut->value,
            StockMovementType::ProductionLossOut->value,
            StockMovementType::ProductionReceiptIn->value,
        ])->count())->toBe(0)
        ->and(m14Balance($materialA, $warehouse, $location)->quantity)->toBe('10.000000')
        ->and(m14Balance($materialB, $warehouse, $location)->quantity)->toBe('20.000000');
});

it('rejects recipe self-consumption and loss payload drift', function (): void {
    [$company, $materialA, , $finishedGood, $warehouse, $location] = m14Fixture('M14-C');
    $ops = app(ProductionOperations::class);

    expect(fn () => $ops->createRecipe(
        (int) $company->getKey(),
        (int) $finishedGood->getKey(),
        'SELF',
        'Self Recipe',
        '1',
        [['product_id' => (int) $finishedGood->getKey(), 'quantity' => '1']],
    ))->toThrow(DomainException::class);

    $recipe = $ops->createRecipe(
        (int) $company->getKey(),
        (int) $finishedGood->getKey(),
        'REC-DRIFT',
        'Drift Recipe',
        '1',
        [['product_id' => (int) $materialA->getKey(), 'quantity' => '1']],
    );
    $order = $ops->createOrder(
        (int) $company->getKey(),
        (int) $recipe->getKey(),
        'PRD-DRIFT',
        '1',
        (int) $warehouse->getKey(),
        (int) $location->getKey(),
    );
    $ops->issueMaterials((int) $company->getKey(), (int) $order->getKey());
    $ops->recordLoss((int) $company->getKey(), (int) $order->getKey(), 'same-loss-key', (int) $materialA->getKey(), '1', 'missing');

    expect(fn () => $ops->recordLoss(
        (int) $company->getKey(),
        (int) $order->getKey(),
        'same-loss-key',
        (int) $materialA->getKey(),
        '2',
        'missing',
    ))->toThrow(DomainException::class, 'farklı payload');
});

/** @return array{Company, Product, Product, Product, Warehouse, WarehouseLocation} */
function m14Fixture(string $code): array
{
    $company = Company::query()->create(['code' => $code, 'name' => 'Company '.$code]);
    $category = Category::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'PROD',
        'name' => 'Üretim',
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

    $materialA = m14Product($company, $category, $unit, $tax, 'MAT-A-'.$code, 'Malzeme A '.$code);
    $materialB = m14Product($company, $category, $unit, $tax, 'MAT-B-'.$code, 'Malzeme B '.$code);
    $finishedGood = m14Product($company, $category, $unit, $tax, 'FG-'.$code, 'Mamul '.$code);

    $warehouse = Warehouse::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'MAIN',
        'name' => 'Merkez Depo',
        'is_active' => true,
    ]);
    $location = WarehouseLocation::query()->create([
        'company_id' => $company->getKey(),
        'warehouse_id' => $warehouse->getKey(),
        'code' => 'PROD-01',
        'name' => 'Üretim Rafı',
        'is_active' => true,
    ]);

    m14Opening($company, $materialA, $warehouse, $location, '10', '5', 'A-'.$code);
    m14Opening($company, $materialB, $warehouse, $location, '20', '2', 'B-'.$code);

    return [$company, $materialA, $materialB, $finishedGood, $warehouse, $location];
}

function m14Product(Company $company, Category $category, Unit $unit, Tax $tax, string $code, string $name): Product
{
    return Product::query()->create([
        'company_id' => $company->getKey(),
        'code' => $code,
        'status' => ProductStatus::Active,
        'name' => $name,
        'category_id' => $category->getKey(),
        'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(),
        'sale_price_net' => '100.000000',
        'purchase_price_net' => '10.000000',
    ]);
}

function m14Opening(Company $company, Product $product, Warehouse $warehouse, WarehouseLocation $location, string $quantity, string $unitCost, string $sourceId): void
{
    DB::transaction(fn () => app(StockMovementPoster::class)->post(new PostStockMovementData(
        sourceEffect: new SourceEffectIdentity(
            (int) $company->getKey(),
            'production.fixture',
            $sourceId,
            'inventory.opening',
        ),
        productId: (int) $product->getKey(),
        warehouseId: (int) $warehouse->getKey(),
        locationId: (int) $location->getKey(),
        movementType: StockMovementType::OpeningIn,
        quantity: $quantity,
        unitCost: $unitCost,
    )));
}

function m14Balance(Product $product, Warehouse $warehouse, WarehouseLocation $location): StockBalance
{
    return StockBalance::query()
        ->where('product_id', $product->getKey())
        ->where('warehouse_id', $warehouse->getKey())
        ->where('location_id', $location->getKey())
        ->firstOrFail();
}
