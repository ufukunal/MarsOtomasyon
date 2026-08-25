<?php

use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Tax;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Ledger\PostStockMovementData;
use App\Modules\Inventory\Ledger\StockMovementPoster;
use App\Modules\Inventory\Ledger\StockMovementReverser;
use App\Modules\Inventory\Ledger\StockMovementPostingResult;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

uses(DatabaseMigrations::class);

it('reverses an inbound movement with its original carrying cost instead of the current average', function (): void {
    [$company, $product, $warehouse, $location] = m44StockContext('M44-IN');

    $first = m44Post($company, $product, $warehouse, $location, 'opening-100', StockMovementType::OpeningIn, '10', '100');
    $second = m44Post($company, $product, $warehouse, $location, 'receipt-200', StockMovementType::AdjustmentIn, '10', '200');

    $before = StockBalance::query()->where('product_id', $product->getKey())->firstOrFail();
    expect($before->quantity)->toBe('20.000000')
        ->and($before->average_unit_cost)->toBe('150.000000')
        ->and($before->inventory_value)->toBe('3000.000000');

    $identity = m44Identity($company, 'receipt-200-cancel', 'inventory.reversal');
    $reversal = DB::transaction(fn (): StockMovementPostingResult => app(StockMovementReverser::class)->reverse(
        originalMovementId: (int) $second->movement->getKey(),
        sourceEffect: $identity,
        note: 'Receipt cancel',
    ));

    $replay = DB::transaction(fn (): StockMovementPostingResult => app(StockMovementReverser::class)->reverse(
        originalMovementId: (int) $second->movement->getKey(),
        sourceEffect: $identity,
        note: 'Receipt cancel',
    ));

    $balance = $before->refresh();
    expect($first->movement->unit_cost)->toBe('100.000000')
        ->and($reversal->replayed)->toBeFalse()
        ->and($replay->replayed)->toBeTrue()
        ->and($replay->movement->getKey())->toBe($reversal->movement->getKey())
        ->and($reversal->movement->movement_type)->toBe(StockMovementType::ReversalOut)
        ->and($reversal->movement->reversal_of_movement_id)->toBe($second->movement->getKey())
        ->and($reversal->movement->quantity_delta)->toBe('-10.000000')
        ->and($reversal->movement->unit_cost)->toBe('200.000000')
        ->and($reversal->movement->value_delta)->toBe('-2000.000000')
        ->and($balance->quantity)->toBe('10.000000')
        ->and($balance->average_unit_cost)->toBe('100.000000')
        ->and($balance->inventory_value)->toBe('1000.000000')
        ->and(StockMovement::query()->count())->toBe(3);

    expect(fn () => DB::transaction(fn (): StockMovementPostingResult => app(StockMovementReverser::class)->reverse(
        originalMovementId: (int) $second->movement->getKey(),
        sourceEffect: m44Identity($company, 'receipt-200-second-cancel', 'inventory.reversal'),
    )))->toThrow(DomainException::class);
});

it('restores an outbound movement at its original outbound carrying cost after the average changes', function (): void {
    [$company, $product, $warehouse, $location] = m44StockContext('M44-OUT');

    m44Post($company, $product, $warehouse, $location, 'opening', StockMovementType::OpeningIn, '10', '100');
    $out = m44Post($company, $product, $warehouse, $location, 'issue', StockMovementType::AdjustmentOut, '4');
    m44Post($company, $product, $warehouse, $location, 'later-receipt', StockMovementType::AdjustmentIn, '4', '200');

    $before = StockBalance::query()->where('product_id', $product->getKey())->firstOrFail();
    expect($out->movement->unit_cost)->toBe('100.000000')
        ->and($out->movement->value_delta)->toBe('-400.000000')
        ->and($before->quantity)->toBe('10.000000')
        ->and($before->average_unit_cost)->toBe('140.000000')
        ->and($before->inventory_value)->toBe('1400.000000');

    $reversal = DB::transaction(fn (): StockMovementPostingResult => app(StockMovementReverser::class)->reverse(
        originalMovementId: (int) $out->movement->getKey(),
        sourceEffect: m44Identity($company, 'issue-cancel', 'inventory.reversal'),
    ));

    $balance = $before->refresh();
    expect($reversal->movement->movement_type)->toBe(StockMovementType::ReversalIn)
        ->and($reversal->movement->quantity_delta)->toBe('4.000000')
        ->and($reversal->movement->unit_cost)->toBe('100.000000')
        ->and($reversal->movement->value_delta)->toBe('400.000000')
        ->and($balance->quantity)->toBe('14.000000')
        ->and($balance->average_unit_cost)->toBe('128.571429')
        ->and($balance->inventory_value)->toBe('1800.000000');
});

it('blocks an inbound reversal when exact original quantity and value can no longer be removed safely', function (): void {
    [$company, $product, $warehouse, $location] = m44StockContext('M44-SAFE');

    $opening = m44Post($company, $product, $warehouse, $location, 'opening', StockMovementType::OpeningIn, '10', '100');
    m44Post($company, $product, $warehouse, $location, 'consumed', StockMovementType::AdjustmentOut, '5');

    expect(fn () => DB::transaction(fn (): StockMovementPostingResult => app(StockMovementReverser::class)->reverse(
        originalMovementId: (int) $opening->movement->getKey(),
        sourceEffect: m44Identity($company, 'opening-cancel', 'inventory.reversal'),
    )))->toThrow(ValidationException::class);

    $balance = StockBalance::query()->where('product_id', $product->getKey())->firstOrFail();
    expect($balance->quantity)->toBe('5.000000')
        ->and($balance->average_unit_cost)->toBe('100.000000')
        ->and($balance->inventory_value)->toBe('500.000000')
        ->and(StockMovement::query()->count())->toBe(2);
});

it('enforces exact reversal lineage at the PostgreSQL boundary', function (): void {
    [$company, $product, $warehouse, $location] = m44StockContext('M44-DB');
    $opening = m44Post($company, $product, $warehouse, $location, 'opening', StockMovementType::OpeningIn, '2', '50');

    expect(fn () => DB::table('stock_movements')->insert([
        'company_id' => $company->getKey(),
        'operation_key' => str_repeat('a', 64),
        'request_fingerprint' => str_repeat('b', 64),
        'source_type' => 'inventory.direct',
        'source_id' => 'bad-reversal',
        'effect_type' => 'inventory.reversal',
        'reversal_of_movement_id' => $opening->movement->getKey(),
        'product_id' => $product->getKey(),
        'warehouse_id' => $warehouse->getKey(),
        'location_id' => $location->getKey(),
        'movement_type' => StockMovementType::ReversalOut->value,
        'quantity_delta' => '-2.000000',
        'unit_cost' => '50.000000',
        'value_delta' => '-99.000000',
        'balance_quantity_after' => '0.000000',
        'average_unit_cost_after' => '0.000000',
        'inventory_value_after' => '0.000000',
        'note' => null,
        'occurred_at' => now(),
        'created_at' => now(),
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table('stock_movements')->insert([
        'company_id' => $company->getKey(),
        'operation_key' => str_repeat('c', 64),
        'request_fingerprint' => str_repeat('d', 64),
        'source_type' => 'inventory.direct',
        'source_id' => 'missing-lineage',
        'effect_type' => 'inventory.reversal',
        'reversal_of_movement_id' => null,
        'product_id' => $product->getKey(),
        'warehouse_id' => $warehouse->getKey(),
        'location_id' => $location->getKey(),
        'movement_type' => StockMovementType::ReversalOut->value,
        'quantity_delta' => '-2.000000',
        'unit_cost' => '50.000000',
        'value_delta' => '-100.000000',
        'balance_quantity_after' => '0.000000',
        'average_unit_cost_after' => '0.000000',
        'inventory_value_after' => '0.000000',
        'note' => null,
        'occurred_at' => now(),
        'created_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('requires reversal to run inside the owning business transaction', function (): void {
    [$company, $product, $warehouse, $location] = m44StockContext('M44-TX');
    $opening = m44Post($company, $product, $warehouse, $location, 'opening', StockMovementType::OpeningIn, '1', '20');

    expect(fn () => app(StockMovementReverser::class)->reverse(
        originalMovementId: (int) $opening->movement->getKey(),
        sourceEffect: m44Identity($company, 'outside-tx', 'inventory.reversal'),
    ))->toThrow(LogicException::class, 'aynı business transaction');
});

function m44Post(
    Company $company,
    Product $product,
    Warehouse $warehouse,
    WarehouseLocation $location,
    string $sourceId,
    StockMovementType $type,
    string $quantity,
    ?string $unitCost = null,
): StockMovementPostingResult {
    return DB::transaction(fn (): StockMovementPostingResult => app(StockMovementPoster::class)->post(new PostStockMovementData(
        sourceEffect: m44Identity($company, $sourceId, 'inventory.'.str_replace('_', '.', $type->value)),
        productId: (int) $product->getKey(),
        warehouseId: (int) $warehouse->getKey(),
        locationId: (int) $location->getKey(),
        movementType: $type,
        quantity: $quantity,
        unitCost: $unitCost,
    )));
}

function m44Identity(Company $company, string $sourceId, string $effectType): SourceEffectIdentity
{
    return new SourceEffectIdentity(
        companyId: (int) $company->getKey(),
        sourceType: 'inventory.m44-test',
        sourceId: $sourceId,
        effectType: $effectType,
    );
}

/** @return array{Company, Product, Warehouse, WarehouseLocation} */
function m44StockContext(string $code): array
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
