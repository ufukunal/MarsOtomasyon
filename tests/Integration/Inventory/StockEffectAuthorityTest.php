<?php

use App\Foundation\Identity\SourceEffectIdentity;
use App\Foundation\Idempotency\IdempotencyConflict;
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
use LogicException;

uses(DatabaseMigrations::class);

it('requires stock effects to post inside the owning business transaction', function (): void {
    [$company, $product, $warehouse, $location] = m42StockContext('M42-TX');

    $data = new PostStockMovementData(
        sourceEffect: new SourceEffectIdentity((int) $company->getKey(), 'inventory.test', 'tx-1', 'inventory.opening'),
        productId: (int) $product->getKey(),
        warehouseId: (int) $warehouse->getKey(),
        locationId: (int) $location->getKey(),
        movementType: StockMovementType::OpeningIn,
        quantity: '2',
        unitCost: '50',
    );

    expect(fn () => app(StockMovementPoster::class)->post($data))
        ->toThrow(LogicException::class, 'aynı business transaction');
});

it('posts a source effect exactly once and rejects payload drift on replay', function (): void {
    [$company, $product, $warehouse, $location] = m42StockContext('M42-IDEM');
    $identity = new SourceEffectIdentity(
        (int) $company->getKey(),
        'purchase.receipt',
        'receipt-line-42',
        'inventory.stock_in',
    );

    $data = new PostStockMovementData(
        sourceEffect: $identity,
        productId: (int) $product->getKey(),
        warehouseId: (int) $warehouse->getKey(),
        locationId: (int) $location->getKey(),
        movementType: StockMovementType::OpeningIn,
        quantity: '3',
        unitCost: '70',
        note: 'Canonical effect',
    );

    $first = DB::transaction(fn () => app(StockMovementPoster::class)->post($data));
    $replay = DB::transaction(fn () => app(StockMovementPoster::class)->post($data));

    expect($first->replayed)->toBeFalse()
        ->and($replay->replayed)->toBeTrue()
        ->and($replay->movement->getKey())->toBe($first->movement->getKey())
        ->and(StockMovement::query()->count())->toBe(1)
        ->and($first->movement->source_type)->toBe('purchase.receipt')
        ->and($first->movement->source_id)->toBe('receipt-line-42')
        ->and($first->movement->effect_type)->toBe('inventory.stock_in');

    $drifted = new PostStockMovementData(
        sourceEffect: $identity,
        productId: (int) $product->getKey(),
        warehouseId: (int) $warehouse->getKey(),
        locationId: (int) $location->getKey(),
        movementType: StockMovementType::OpeningIn,
        quantity: '4',
        unitCost: '70',
        note: 'Canonical effect',
    );

    expect(fn () => DB::transaction(fn () => app(StockMovementPoster::class)->post($drifted)))
        ->toThrow(IdempotencyConflict::class);

    expect(StockMovement::query()->count())->toBe(1);
});

it('supports transfer issue and receipt effects while preserving carrying value', function (): void {
    [$company, $product, $warehouse, $source] = m42StockContext('M42-XFER');
    $destination = WarehouseLocation::query()->create([
        'company_id' => $company->getKey(),
        'warehouse_id' => $warehouse->getKey(),
        'code' => 'B-01',
        'name' => 'B Rafı',
        'is_active' => true,
    ]);

    DB::transaction(fn () => app(StockMovementPoster::class)->post(new PostStockMovementData(
        sourceEffect: new SourceEffectIdentity((int) $company->getKey(), 'inventory.test', 'opening', 'inventory.opening'),
        productId: (int) $product->getKey(),
        warehouseId: (int) $warehouse->getKey(),
        locationId: (int) $source->getKey(),
        movementType: StockMovementType::OpeningIn,
        quantity: '10',
        unitCost: '100',
    )));

    $issue = DB::transaction(fn () => app(StockMovementPoster::class)->post(new PostStockMovementData(
        sourceEffect: new SourceEffectIdentity((int) $company->getKey(), 'inventory.transfer', 'transfer-42', 'inventory.transfer_out'),
        productId: (int) $product->getKey(),
        warehouseId: (int) $warehouse->getKey(),
        locationId: (int) $source->getKey(),
        movementType: StockMovementType::TransferOut,
        quantity: '4',
    )));

    $receipt = DB::transaction(fn () => app(StockMovementPoster::class)->post(new PostStockMovementData(
        sourceEffect: new SourceEffectIdentity((int) $company->getKey(), 'inventory.transfer', 'transfer-42', 'inventory.transfer_in'),
        productId: (int) $product->getKey(),
        warehouseId: (int) $warehouse->getKey(),
        locationId: (int) $destination->getKey(),
        movementType: StockMovementType::TransferIn,
        quantity: '4',
        unitCost: $issue->movement->unit_cost,
    )));

    $sourceBalance = StockBalance::query()->where('location_id', $source->getKey())->firstOrFail();
    $destinationBalance = StockBalance::query()->where('location_id', $destination->getKey())->firstOrFail();

    expect($issue->movement->unit_cost)->toBe('100.000000')
        ->and($issue->movement->value_delta)->toBe('-400.000000')
        ->and($receipt->movement->unit_cost)->toBe('100.000000')
        ->and($receipt->movement->value_delta)->toBe('400.000000')
        ->and($sourceBalance->quantity)->toBe('6.000000')
        ->and($sourceBalance->inventory_value)->toBe('600.000000')
        ->and($destinationBalance->quantity)->toBe('4.000000')
        ->and($destinationBalance->inventory_value)->toBe('400.000000');
});

it('enforces canonical source effect identity at PostgreSQL level', function (): void {
    [$company, $product, $warehouse, $location] = m42StockContext('M42-DB');

    expect(fn () => DB::table('stock_movements')->insert([
        'company_id' => $company->getKey(),
        'operation_key' => str_repeat('a', 64),
        'request_fingerprint' => str_repeat('b', 64),
        'source_type' => 'inventory.valid!',
        'source_id' => 'raw-row',
        'effect_type' => 'inventory.raw',
        'product_id' => $product->getKey(),
        'warehouse_id' => $warehouse->getKey(),
        'location_id' => $location->getKey(),
        'movement_type' => StockMovementType::OpeningIn->value,
        'quantity_delta' => '1',
        'unit_cost' => '1',
        'value_delta' => '1',
        'balance_quantity_after' => '1',
        'average_unit_cost_after' => '1',
        'inventory_value_after' => '1',
        'note' => null,
        'occurred_at' => now(),
        'created_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('backfills M4.1 legacy movement identity during the authority migration', function (): void {
    [$company, $product, $warehouse, $location] = m42StockContext('M42-UPGRADE');
    $migration = require database_path('migrations/2026_08_25_005700_add_stock_source_effect_identity.php');

    $migration->down();

    DB::table('stock_movements')->insert([
        'company_id' => $company->getKey(),
        'operation_key' => 'legacy-open',
        'request_fingerprint' => str_repeat('c', 64),
        'product_id' => $product->getKey(),
        'warehouse_id' => $warehouse->getKey(),
        'location_id' => $location->getKey(),
        'movement_type' => 'opening_in',
        'quantity_delta' => '2',
        'unit_cost' => '25',
        'value_delta' => '50',
        'balance_quantity_after' => '2',
        'average_unit_cost_after' => '25',
        'inventory_value_after' => '50',
        'note' => 'Legacy',
        'occurred_at' => now(),
        'created_at' => now(),
    ]);

    $migration->up();

    $legacy = DB::table('stock_movements')->where('operation_key', 'legacy-open')->first();
    expect($legacy)->not->toBeNull()
        ->and($legacy->source_type)->toBe('inventory.manual_stock')
        ->and($legacy->source_id)->toBe('legacy-open')
        ->and($legacy->effect_type)->toBe('inventory.opening_in');
});

/** @return array{Company, Product, Warehouse, WarehouseLocation} */
function m42StockContext(string $code): array
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
