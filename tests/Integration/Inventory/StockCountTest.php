<?php

use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Tax;
use App\Modules\Inventory\Counts\StockCountService;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Ledger\PostStockMovementData;
use App\Modules\Inventory\Ledger\StockMovementPoster;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockCountLine;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Barcode;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

uses(DatabaseMigrations::class);

it('finalizes a negative count variance through append-only adjustment out', function (): void {
    [$company, $product, $warehouse, $location] = m47Context('M47-OUT');
    m47Post($company, $product, $warehouse, $location, StockMovementType::OpeningIn, '10', '100', 'opening');
    $count = DB::transaction(fn (): StockCount => app(StockCountService::class)->start(
        (int) $company->getKey(),
        (int) $location->getKey(),
        'count-out',
    ));

    $line = StockCountLine::query()->where('stock_count_id', $count->getKey())->firstOrFail();
    expect($line->expected_quantity)->toBe('10.000000')
        ->and($line->expected_unit_cost)->toBe('100.000000')
        ->and($line->expected_value)->toBe('1000.000000');

    DB::transaction(fn (): StockCountLine => app(StockCountService::class)->setCounted(
        (int) $company->getKey(),
        (int) $count->getKey(),
        (int) $product->getKey(),
        '8',
    ));
    $posted = DB::transaction(fn (): StockCount => app(StockCountService::class)->post(
        (int) $company->getKey(),
        (int) $count->getKey(),
    ));

    $line->refresh();
    $balance = m47Balance($product, $location);
    $movement = StockMovement::query()->findOrFail($line->adjustment_movement_id);

    expect($posted->status)->toBe('posted')
        ->and($posted->posted_at)->not->toBeNull()
        ->and($line->counted_quantity)->toBe('8.000000')
        ->and($line->variance_quantity)->toBe('-2.000000')
        ->and($movement->movement_type)->toBe(StockMovementType::AdjustmentOut)
        ->and($movement->quantity_delta)->toBe('-2.000000')
        ->and($movement->value_delta)->toBe('-200.000000')
        ->and($balance->quantity)->toBe('8.000000')
        ->and($balance->inventory_value)->toBe('800.000000');

    $replay = DB::transaction(fn (): StockCount => app(StockCountService::class)->post(
        (int) $company->getKey(),
        (int) $count->getKey(),
    ));
    expect($replay->status)->toBe('posted')
        ->and(StockMovement::query()->count())->toBe(2);
});

it('quick counts exact barcodes and requires valuation for positive variance without snapshot cost', function (): void {
    [$company, $product, , $location] = m47Context('M47-QUICK');
    Barcode::query()->create([
        'company_id' => $company->getKey(),
        'product_id' => $product->getKey(),
        'barcode' => '8690000000471',
        'is_primary' => true,
    ]);
    $count = DB::transaction(fn (): StockCount => app(StockCountService::class)->start(
        (int) $company->getKey(),
        (int) $location->getKey(),
        'count-quick',
    ));

    $first = DB::transaction(fn (): StockCountLine => app(StockCountService::class)->scanBarcode(
        (int) $company->getKey(),
        (int) $count->getKey(),
        '8690000000471',
    ));
    $second = DB::transaction(fn (): StockCountLine => app(StockCountService::class)->scanBarcode(
        (int) $company->getKey(),
        (int) $count->getKey(),
        '8690000000471',
    ));

    expect($first->expected_quantity)->toBe('0.000000')
        ->and($second->counted_quantity)->toBe('2.000000')
        ->and($second->variance_quantity)->toBe('2.000000');

    expect(fn () => DB::transaction(fn (): StockCount => app(StockCountService::class)->post(
        (int) $company->getKey(),
        (int) $count->getKey(),
    )))->toThrow(ValidationException::class);

    DB::transaction(fn (): StockCountLine => app(StockCountService::class)->setCounted(
        (int) $company->getKey(),
        (int) $count->getKey(),
        (int) $product->getKey(),
        '2',
        '50',
    ));
    DB::transaction(fn (): StockCount => app(StockCountService::class)->post(
        (int) $company->getKey(),
        (int) $count->getKey(),
    ));

    $line = StockCountLine::query()->firstOrFail();
    $movement = StockMovement::query()->findOrFail($line->adjustment_movement_id);
    $balance = m47Balance($product, $location);

    expect($movement->movement_type)->toBe(StockMovementType::AdjustmentIn)
        ->and($movement->unit_cost)->toBe('50.000000')
        ->and($movement->value_delta)->toBe('100.000000')
        ->and($balance->quantity)->toBe('2.000000')
        ->and($balance->inventory_value)->toBe('100.000000');
});

it('rejects stale count snapshots after an intervening physical stock movement', function (): void {
    [$company, $product, $warehouse, $location] = m47Context('M47-STALE');
    m47Post($company, $product, $warehouse, $location, StockMovementType::OpeningIn, '5', '20', 'opening');
    $count = DB::transaction(fn (): StockCount => app(StockCountService::class)->start(
        (int) $company->getKey(),
        (int) $location->getKey(),
        'count-stale',
    ));
    DB::transaction(fn (): StockCountLine => app(StockCountService::class)->setCounted(
        (int) $company->getKey(),
        (int) $count->getKey(),
        (int) $product->getKey(),
        '5',
    ));

    m47Post($company, $product, $warehouse, $location, StockMovementType::AdjustmentIn, '1', '30', 'after-snapshot');

    expect(fn () => DB::transaction(fn (): StockCount => app(StockCountService::class)->post(
        (int) $company->getKey(),
        (int) $count->getKey(),
    )))->toThrow(ValidationException::class);

    expect($count->refresh()->status)->toBe('draft')
        ->and(StockCountLine::query()->firstOrFail()->adjustment_movement_id)->toBeNull()
        ->and(StockMovement::query()->count())->toBe(2)
        ->and(m47Balance($product, $location)->quantity)->toBe('6.000000');
});

it('rejects a newly arrived unsnapshotted product even when it was never touched by the counter', function (): void {
    [$company, $product, $warehouse, $location] = m47Context('M47-NEW');
    $secondProduct = m47Product($company, 'SECOND');
    m47Post($company, $product, $warehouse, $location, StockMovementType::OpeningIn, '3', '10', 'opening-one');
    $count = DB::transaction(fn (): StockCount => app(StockCountService::class)->start(
        (int) $company->getKey(),
        (int) $location->getKey(),
        'count-new-product',
    ));
    DB::transaction(fn (): StockCountLine => app(StockCountService::class)->setCounted(
        (int) $company->getKey(),
        (int) $count->getKey(),
        (int) $product->getKey(),
        '3',
    ));

    m47Post($company, $secondProduct, $warehouse, $location, StockMovementType::OpeningIn, '1', '15', 'new-product');

    expect(fn () => DB::transaction(fn (): StockCount => app(StockCountService::class)->post(
        (int) $company->getKey(),
        (int) $count->getKey(),
    )))->toThrow(ValidationException::class);
});

it('enforces stock count transaction and PostgreSQL immutability boundaries', function (): void {
    [$company, $product, $warehouse, $location] = m47Context('M47-GUARD');
    m47Post($company, $product, $warehouse, $location, StockMovementType::OpeningIn, '2', '25', 'opening');
    $service = app(StockCountService::class);

    expect(fn () => $service->start(
        (int) $company->getKey(),
        (int) $location->getKey(),
        'outside-tx',
    ))->toThrow(LogicException::class);

    $count = DB::transaction(fn (): StockCount => $service->start(
        (int) $company->getKey(),
        (int) $location->getKey(),
        'count-guard',
    ));
    $replay = DB::transaction(fn (): StockCount => $service->start(
        (int) $company->getKey(),
        (int) $location->getKey(),
        'count-guard',
    ));
    expect($replay->getKey())->toBe($count->getKey());

    DB::transaction(fn (): StockCountLine => $service->setCounted(
        (int) $company->getKey(),
        (int) $count->getKey(),
        (int) $product->getKey(),
        '1',
    ));
    DB::transaction(fn (): StockCount => $service->post((int) $company->getKey(), (int) $count->getKey()));
    $line = StockCountLine::query()->firstOrFail();

    expect(fn () => DB::table('stock_counts')->where('id', $count->getKey())->update([
        'status' => 'draft',
        'posted_at' => null,
    ]))->toThrow(QueryException::class);
    expect(fn () => DB::table('stock_count_lines')->where('id', $line->getKey())->update([
        'counted_quantity' => '2.000000',
    ]))->toThrow(QueryException::class);
    expect(fn () => DB::table('stock_count_lines')->where('id', $line->getKey())->delete())
        ->toThrow(QueryException::class);
});

function m47Post(
    Company $company,
    Product $product,
    Warehouse $warehouse,
    WarehouseLocation $location,
    StockMovementType $type,
    string $quantity,
    string $unitCost,
    string $sourceId,
): void {
    DB::transaction(fn () => app(StockMovementPoster::class)->post(new PostStockMovementData(
        sourceEffect: new SourceEffectIdentity(
            companyId: (int) $company->getKey(),
            sourceType: 'inventory.test-count',
            sourceId: $sourceId,
            effectType: 'inventory.count-fixture',
        ),
        productId: (int) $product->getKey(),
        warehouseId: (int) $warehouse->getKey(),
        locationId: (int) $location->getKey(),
        movementType: $type,
        quantity: $quantity,
        unitCost: $unitCost,
    )));
}

function m47Balance(Product $product, WarehouseLocation $location): StockBalance
{
    return StockBalance::query()
        ->where('product_id', $product->getKey())
        ->where('location_id', $location->getKey())
        ->firstOrFail();
}

function m47Product(Company $company, string $code): Product
{
    $category = Category::query()->where('company_id', $company->getKey())->firstOrFail();
    $unit = Unit::query()->where('company_id', $company->getKey())->firstOrFail();
    $tax = Tax::query()->where('company_id', $company->getKey())->firstOrFail();

    return Product::query()->create([
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
}

/** @return array{Company, Product, Warehouse, WarehouseLocation} */
function m47Context(string $code): array
{
    $company = Company::query()->create(['code' => $code, 'name' => 'Company '.$code]);
    Category::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'LIGHT',
        'name' => 'Aydınlatma',
        'is_active' => true,
    ]);
    Unit::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'ADET',
        'name' => 'Adet',
        'is_active' => true,
    ]);
    Tax::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'KDV20',
        'name' => 'KDV %20',
        'rate' => '20.000000',
        'is_active' => true,
    ]);
    $product = m47Product($company, $code);
    $warehouse = Warehouse::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'WH',
        'name' => 'Ana Depo',
        'is_active' => true,
    ]);
    $location = WarehouseLocation::query()->create([
        'company_id' => $company->getKey(),
        'warehouse_id' => $warehouse->getKey(),
        'code' => 'A-01',
        'name' => 'Sayım Rafı',
        'is_active' => true,
    ]);

    return [$company, $product, $warehouse, $location];
}
