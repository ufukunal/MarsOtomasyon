<?php

use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Tax;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Ledger\PostStockMovementData;
use App\Modules\Inventory\Ledger\StockMovementPoster;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(DatabaseMigrations::class);

it('derives available quantity from physical reserved and blocked projections', function (): void {
    [$company, $product, $warehouse, $location] = m43StockContext('M43-PROJECTION');
    m43PostOpening($company, $product, $warehouse, $location, '10', '80');

    $balance = StockBalance::query()->where('product_id', $product->getKey())->firstOrFail();
    expect($balance->quantity)->toBe('10.000000')
        ->and($balance->reserved_quantity)->toBe('0.000000')
        ->and($balance->blocked_quantity)->toBe('0.000000')
        ->and($balance->available_quantity)->toBe('10.000000');

    DB::table('stock_balances')->where('id', $balance->getKey())->update([
        'reserved_quantity' => '3.000000',
        'blocked_quantity' => '2.000000',
    ]);

    $balance->refresh();
    expect($balance->quantity)->toBe('10.000000')
        ->and($balance->reserved_quantity)->toBe('3.000000')
        ->and($balance->blocked_quantity)->toBe('2.000000')
        ->and($balance->available_quantity)->toBe('5.000000');
});

it('keeps available quantity database generated and rejects over-allocation', function (): void {
    [$company, $product, $warehouse, $location] = m43StockContext('M43-DB');
    m43PostOpening($company, $product, $warehouse, $location, '5', '20');

    $balance = StockBalance::query()->where('product_id', $product->getKey())->firstOrFail();

    expect(fn () => DB::table('stock_balances')->where('id', $balance->getKey())->update([
        'reserved_quantity' => '4.000000',
        'blocked_quantity' => '2.000000',
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table('stock_balances')->where('id', $balance->getKey())->update([
        'available_quantity' => '999.000000',
    ]))->toThrow(QueryException::class);

    $balance->refresh();
    expect($balance->quantity)->toBe('5.000000')
        ->and($balance->reserved_quantity)->toBe('0.000000')
        ->and($balance->blocked_quantity)->toBe('0.000000')
        ->and($balance->available_quantity)->toBe('5.000000');
});

it('blocks outbound stock against available instead of raw physical quantity', function (): void {
    [$company, $product, $warehouse, $location] = m43StockContext('M43-OUT');
    m43PostOpening($company, $product, $warehouse, $location, '10', '100');

    $balance = StockBalance::query()->where('product_id', $product->getKey())->firstOrFail();
    DB::table('stock_balances')->where('id', $balance->getKey())->update([
        'reserved_quantity' => '3.000000',
        'blocked_quantity' => '2.000000',
    ]);

    expect(fn () => DB::transaction(fn () => app(StockMovementPoster::class)->post(new PostStockMovementData(
        sourceEffect: new SourceEffectIdentity(
            (int) $company->getKey(),
            'inventory.test',
            'out-over-available',
            'inventory.adjustment_out',
        ),
        productId: (int) $product->getKey(),
        warehouseId: (int) $warehouse->getKey(),
        locationId: (int) $location->getKey(),
        movementType: StockMovementType::AdjustmentOut,
        quantity: '6',
    ))))->toThrow(ValidationException::class);

    $balance->refresh();
    expect($balance->quantity)->toBe('10.000000')
        ->and($balance->available_quantity)->toBe('5.000000')
        ->and(StockMovement::query()->count())->toBe(1);

    DB::transaction(fn () => app(StockMovementPoster::class)->post(new PostStockMovementData(
        sourceEffect: new SourceEffectIdentity(
            (int) $company->getKey(),
            'inventory.test',
            'out-exact-available',
            'inventory.adjustment_out',
        ),
        productId: (int) $product->getKey(),
        warehouseId: (int) $warehouse->getKey(),
        locationId: (int) $location->getKey(),
        movementType: StockMovementType::AdjustmentOut,
        quantity: '5',
    )));

    $balance->refresh();
    expect($balance->quantity)->toBe('5.000000')
        ->and($balance->reserved_quantity)->toBe('3.000000')
        ->and($balance->blocked_quantity)->toBe('2.000000')
        ->and($balance->available_quantity)->toBe('0.000000')
        ->and($balance->inventory_value)->toBe('500.000000');
});

it('increases available automatically when new physical stock arrives', function (): void {
    [$company, $product, $warehouse, $location] = m43StockContext('M43-IN');
    m43PostOpening($company, $product, $warehouse, $location, '10', '50');

    $balance = StockBalance::query()->where('product_id', $product->getKey())->firstOrFail();
    DB::table('stock_balances')->where('id', $balance->getKey())->update([
        'reserved_quantity' => '3.000000',
        'blocked_quantity' => '2.000000',
    ]);

    DB::transaction(fn () => app(StockMovementPoster::class)->post(new PostStockMovementData(
        sourceEffect: new SourceEffectIdentity(
            (int) $company->getKey(),
            'inventory.test',
            'in-more',
            'inventory.adjustment_in',
        ),
        productId: (int) $product->getKey(),
        warehouseId: (int) $warehouse->getKey(),
        locationId: (int) $location->getKey(),
        movementType: StockMovementType::AdjustmentIn,
        quantity: '2',
        unitCost: '50',
    )));

    $balance->refresh();
    expect($balance->quantity)->toBe('12.000000')
        ->and($balance->reserved_quantity)->toBe('3.000000')
        ->and($balance->blocked_quantity)->toBe('2.000000')
        ->and($balance->available_quantity)->toBe('7.000000')
        ->and($balance->inventory_value)->toBe('600.000000');
});

function m43PostOpening(
    Company $company,
    Product $product,
    Warehouse $warehouse,
    WarehouseLocation $location,
    string $quantity,
    string $unitCost,
): void {
    DB::transaction(fn () => app(StockMovementPoster::class)->post(new PostStockMovementData(
        sourceEffect: new SourceEffectIdentity(
            (int) $company->getKey(),
            'inventory.test',
            'opening-'.$company->code,
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

/** @return array{Company, Product, Warehouse, WarehouseLocation} */
function m43StockContext(string $code): array
{
    $company = Company::query()->create(['code' => $code, 'name' => 'Company '.$code]);
    $category = Category::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'LIGHT',
        'name' => 'Aydınlatma',
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

    return [$company, $product, $warehouse, $location];
}
