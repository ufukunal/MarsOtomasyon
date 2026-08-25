<?php

use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Tax;
use App\Modules\Inventory\Counts\StockCountService;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Enums\StockReservationStatus;
use App\Modules\Inventory\Ledger\PostStockMovementData;
use App\Modules\Inventory\Ledger\StockMovementPoster;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockCountLine;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockReservation;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Inventory\Models\WarehouseTransfer;
use App\Modules\Inventory\Models\WarehouseTransferLine;
use App\Modules\Inventory\Reservations\StockReservationActionResult;
use App\Modules\Inventory\Reservations\StockReservationService;
use App\Modules\Inventory\Transfers\WarehouseTransferIssueLineData;
use App\Modules\Inventory\Transfers\WarehouseTransferIssueResult;
use App\Modules\Inventory\Transfers\WarehouseTransferReceiptResult;
use App\Modules\Inventory\Transfers\WarehouseTransferService;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(DatabaseMigrations::class);

it('keeps reserved stock unavailable to transfer issue and preserves weighted-average value after release and receipt', function (): void {
    [$company, $product, $sourceWarehouse, $source, $destinationWarehouse, $destination] = m48Context('M48-XFER');
    m48Post($company, $product, $sourceWarehouse, $source, StockMovementType::OpeningIn, '10', '100', 'opening-100');
    m48Post($company, $product, $sourceWarehouse, $source, StockMovementType::AdjustmentIn, '10', '200', 'inbound-200');

    $reservation = DB::transaction(fn (): StockReservationActionResult => app(StockReservationService::class)->reserve(
        m48Identity($company, 'reserve-transfer', 'inventory.reserve'),
        (int) $product->getKey(),
        (int) $sourceWarehouse->getKey(),
        (int) $source->getKey(),
        '5',
    ));

    $sourceBalance = m48Balance($product, $source);
    expect($sourceBalance->quantity)->toBe('20.000000')
        ->and($sourceBalance->average_unit_cost)->toBe('150.000000')
        ->and($sourceBalance->inventory_value)->toBe('3000.000000')
        ->and($sourceBalance->reserved_quantity)->toBe('5.000000')
        ->and($sourceBalance->available_quantity)->toBe('15.000000');

    expect(fn () => DB::transaction(fn (): WarehouseTransferIssueResult => app(WarehouseTransferService::class)->issue(
        m48Identity($company, 'blocked-transfer', 'inventory.transfer_issue'),
        (int) $sourceWarehouse->getKey(),
        (int) $source->getKey(),
        (int) $destinationWarehouse->getKey(),
        (int) $destination->getKey(),
        [new WarehouseTransferIssueLineData((int) $product->getKey(), '16')],
    )))->toThrow(ValidationException::class);

    expect(WarehouseTransfer::query()->count())->toBe(0)
        ->and(StockMovement::query()->count())->toBe(2);

    DB::transaction(fn (): StockReservationActionResult => app(StockReservationService::class)->release(
        m48Identity($company, 'release-transfer', 'inventory.release'),
        (int) $reservation->reservation->getKey(),
    ));

    $issueIdentity = m48Identity($company, 'transfer-16', 'inventory.transfer_issue');
    $issue = DB::transaction(fn (): WarehouseTransferIssueResult => app(WarehouseTransferService::class)->issue(
        $issueIdentity,
        (int) $sourceWarehouse->getKey(),
        (int) $source->getKey(),
        (int) $destinationWarehouse->getKey(),
        (int) $destination->getKey(),
        [new WarehouseTransferIssueLineData((int) $product->getKey(), '16')],
    ));
    $issueReplay = DB::transaction(fn (): WarehouseTransferIssueResult => app(WarehouseTransferService::class)->issue(
        $issueIdentity,
        (int) $sourceWarehouse->getKey(),
        (int) $source->getKey(),
        (int) $destinationWarehouse->getKey(),
        (int) $destination->getKey(),
        [new WarehouseTransferIssueLineData((int) $product->getKey(), '16.000000')],
    ));
    $line = WarehouseTransferLine::query()->where('transfer_id', $issue->transfer->getKey())->firstOrFail();

    $sourceBalance->refresh();
    expect($issue->replayed)->toBeFalse()
        ->and($issueReplay->replayed)->toBeTrue()
        ->and($line->unit_cost)->toBe('150.000000')
        ->and($line->issued_quantity)->toBe('16.000000')
        ->and($line->issued_value)->toBe('2400.000000')
        ->and($line->in_transit_quantity)->toBe('16.000000')
        ->and($line->in_transit_value)->toBe('2400.000000')
        ->and($sourceBalance->quantity)->toBe('4.000000')
        ->and($sourceBalance->inventory_value)->toBe('600.000000')
        ->and($sourceBalance->reserved_quantity)->toBe('0.000000');

    DB::transaction(fn (): WarehouseTransferReceiptResult => app(WarehouseTransferService::class)->receive(
        m48Identity($company, 'receipt-6', 'inventory.transfer_in'),
        (int) $issue->transfer->getKey(),
        (int) $line->getKey(),
        '6',
    ));
    DB::transaction(fn (): WarehouseTransferReceiptResult => app(WarehouseTransferService::class)->receive(
        m48Identity($company, 'receipt-10', 'inventory.transfer_in'),
        (int) $issue->transfer->getKey(),
        (int) $line->getKey(),
        '10',
    ));

    $line->refresh();
    $sourceBalance->refresh();
    $destinationBalance = m48Balance($product, $destination);

    expect($line->received_quantity)->toBe('16.000000')
        ->and($line->received_value)->toBe('2400.000000')
        ->and($line->in_transit_quantity)->toBe('0.000000')
        ->and($line->in_transit_value)->toBe('0.000000')
        ->and($issue->transfer->refresh()->status)->toBe('received')
        ->and($destinationBalance->quantity)->toBe('16.000000')
        ->and($destinationBalance->inventory_value)->toBe('2400.000000')
        ->and($sourceBalance->inventory_value)->toBe('600.000000')
        ->and((string) DB::table('stock_balances')->where('company_id', $company->getKey())->sum('inventory_value'))->toBe('3000.000000');
});

it('does not stale a physical count snapshot when only reservation allocation changes', function (): void {
    [$company, $product, $warehouse, $location] = m48SingleLocationContext('M48-COUNT-RES');
    m48Post($company, $product, $warehouse, $location, StockMovementType::OpeningIn, '5', '20', 'count-res-opening');

    $count = DB::transaction(fn (): StockCount => app(StockCountService::class)->start(
        (int) $company->getKey(),
        (int) $location->getKey(),
        'count-reservation-only',
    ));
    $reservation = DB::transaction(fn (): StockReservationActionResult => app(StockReservationService::class)->reserve(
        m48Identity($company, 'count-reserve-2', 'inventory.reserve'),
        (int) $product->getKey(),
        (int) $warehouse->getKey(),
        (int) $location->getKey(),
        '2',
    ));
    DB::transaction(fn (): StockCountLine => app(StockCountService::class)->setCounted(
        (int) $company->getKey(),
        (int) $count->getKey(),
        (int) $product->getKey(),
        '5',
    ));

    $posted = DB::transaction(fn (): StockCount => app(StockCountService::class)->post(
        (int) $company->getKey(),
        (int) $count->getKey(),
    ));
    $line = StockCountLine::query()->where('stock_count_id', $count->getKey())->firstOrFail();
    $balance = m48Balance($product, $location);

    expect($posted->status)->toBe('posted')
        ->and($line->variance_quantity)->toBe('0.000000')
        ->and($line->adjustment_movement_id)->toBeNull()
        ->and($reservation->reservation->refresh()->statusEnum())->toBe(StockReservationStatus::Active)
        ->and($balance->quantity)->toBe('5.000000')
        ->and($balance->reserved_quantity)->toBe('2.000000')
        ->and($balance->available_quantity)->toBe('3.000000')
        ->and(StockMovement::query()->count())->toBe(1);
});

it('stales a count after transfer issue and never posts a phantom count adjustment', function (): void {
    [$company, $product, $sourceWarehouse, $source, $destinationWarehouse, $destination] = m48Context('M48-COUNT-XFER');
    m48Post($company, $product, $sourceWarehouse, $source, StockMovementType::OpeningIn, '5', '20', 'count-transfer-opening');

    $count = DB::transaction(fn (): StockCount => app(StockCountService::class)->start(
        (int) $company->getKey(),
        (int) $source->getKey(),
        'count-before-transfer',
    ));
    DB::transaction(fn (): StockCountLine => app(StockCountService::class)->setCounted(
        (int) $company->getKey(),
        (int) $count->getKey(),
        (int) $product->getKey(),
        '5',
    ));

    $issue = DB::transaction(fn (): WarehouseTransferIssueResult => app(WarehouseTransferService::class)->issue(
        m48Identity($company, 'count-stale-transfer', 'inventory.transfer_issue'),
        (int) $sourceWarehouse->getKey(),
        (int) $source->getKey(),
        (int) $destinationWarehouse->getKey(),
        (int) $destination->getKey(),
        [new WarehouseTransferIssueLineData((int) $product->getKey(), '1')],
    ));

    expect(fn () => DB::transaction(fn (): StockCount => app(StockCountService::class)->post(
        (int) $company->getKey(),
        (int) $count->getKey(),
    )))->toThrow(ValidationException::class);

    $line = StockCountLine::query()->where('stock_count_id', $count->getKey())->firstOrFail();
    $transferLine = WarehouseTransferLine::query()->where('transfer_id', $issue->transfer->getKey())->firstOrFail();

    expect($count->refresh()->status)->toBe('draft')
        ->and($line->adjustment_movement_id)->toBeNull()
        ->and(m48Balance($product, $source)->quantity)->toBe('4.000000')
        ->and($transferLine->in_transit_quantity)->toBe('1.000000')
        ->and(StockMovement::query()->where('source_type', 'inventory.stock_count')->count())->toBe(0)
        ->and(StockMovement::query()->count())->toBe(2);
});

it('serializes competing reservations and still blocks outbound against remaining available stock', function (): void {
    [$company, $product, $warehouse, $location] = m48SingleLocationContext('M48-CONCURRENCY');
    m48Post($company, $product, $warehouse, $location, StockMovementType::OpeningIn, '5', '10', 'concurrency-opening');

    config(['database.connections.pgsql_m48_concurrent' => config('database.connections.pgsql')]);
    DB::purge('pgsql_m48_concurrent');
    $concurrent = DB::connection('pgsql_m48_concurrent');
    $concurrent->statement("SET lock_timeout TO '150ms'");

    DB::beginTransaction();

    try {
        DB::table('stock_reservations')->insert(m48RawReservation(
            $company,
            $product,
            $warehouse,
            $location,
            '4.000000',
            'lock-holder',
        ));

        expect(fn () => $concurrent->table('stock_reservations')->insert(m48RawReservation(
            $company,
            $product,
            $warehouse,
            $location,
            '2.000000',
            'lock-waiter',
        )))->toThrow(QueryException::class);

        DB::commit();
    } finally {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }

    $concurrent->statement("SET lock_timeout TO '0'");
    expect(fn () => $concurrent->table('stock_reservations')->insert(m48RawReservation(
        $company,
        $product,
        $warehouse,
        $location,
        '2.000000',
        'lock-waiter',
    )))->toThrow(QueryException::class);

    expect(fn () => DB::transaction(fn () => app(StockMovementPoster::class)->post(new PostStockMovementData(
        sourceEffect: m48Identity($company, 'blocked-outbound', 'inventory.adjustment_out'),
        productId: (int) $product->getKey(),
        warehouseId: (int) $warehouse->getKey(),
        locationId: (int) $location->getKey(),
        movementType: StockMovementType::AdjustmentOut,
        quantity: '2',
    ))))->toThrow(ValidationException::class);

    $balance = m48Balance($product, $location);
    expect($balance->quantity)->toBe('5.000000')
        ->and($balance->reserved_quantity)->toBe('4.000000')
        ->and($balance->available_quantity)->toBe('1.000000')
        ->and(StockReservation::query()->count())->toBe(1)
        ->and(StockMovement::query()->count())->toBe(1);

    DB::disconnect('pgsql_m48_concurrent');
});

function m48Identity(Company $company, string $sourceId, string $effectType): SourceEffectIdentity
{
    return new SourceEffectIdentity(
        companyId: (int) $company->getKey(),
        sourceType: 'inventory.exit-gate',
        sourceId: $sourceId,
        effectType: $effectType,
    );
}

function m48Post(
    Company $company,
    Product $product,
    Warehouse $warehouse,
    WarehouseLocation $location,
    StockMovementType $movementType,
    string $quantity,
    ?string $unitCost,
    string $sourceId,
): void {
    DB::transaction(fn () => app(StockMovementPoster::class)->post(new PostStockMovementData(
        sourceEffect: m48Identity($company, $sourceId, 'inventory.stock_fixture'),
        productId: (int) $product->getKey(),
        warehouseId: (int) $warehouse->getKey(),
        locationId: (int) $location->getKey(),
        movementType: $movementType,
        quantity: $quantity,
        unitCost: $unitCost,
    )));
}

function m48Balance(Product $product, WarehouseLocation $location): StockBalance
{
    return StockBalance::query()
        ->where('product_id', $product->getKey())
        ->where('location_id', $location->getKey())
        ->firstOrFail();
}

/** @return array<string, mixed> */
function m48RawReservation(
    Company $company,
    Product $product,
    Warehouse $warehouse,
    WarehouseLocation $location,
    string $quantity,
    string $sourceId,
): array {
    $now = now();

    return [
        'company_id' => (int) $company->getKey(),
        'product_id' => (int) $product->getKey(),
        'warehouse_id' => (int) $warehouse->getKey(),
        'location_id' => (int) $location->getKey(),
        'quantity' => $quantity,
        'status' => StockReservationStatus::Active->value,
        'reserve_source_type' => 'inventory.exit-gate',
        'reserve_source_id' => $sourceId,
        'reserve_effect_type' => 'inventory.reserve',
        'reserved_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ];
}

/** @return array{Company, Product, Warehouse, WarehouseLocation} */
function m48SingleLocationContext(string $code): array
{
    [$company, $product] = m48ProductContext($code);
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
        'name' => 'Ana Raf',
        'is_active' => true,
    ]);

    return [$company, $product, $warehouse, $location];
}

/** @return array{Company, Product, Warehouse, WarehouseLocation, Warehouse, WarehouseLocation} */
function m48Context(string $code): array
{
    [$company, $product] = m48ProductContext($code);
    $sourceWarehouse = Warehouse::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'SRC',
        'name' => 'Kaynak Depo',
        'is_active' => true,
    ]);
    $source = WarehouseLocation::query()->create([
        'company_id' => $company->getKey(),
        'warehouse_id' => $sourceWarehouse->getKey(),
        'code' => 'A-01',
        'name' => 'Kaynak Raf',
        'is_active' => true,
    ]);
    $destinationWarehouse = Warehouse::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'DST',
        'name' => 'Hedef Depo',
        'is_active' => true,
    ]);
    $destination = WarehouseLocation::query()->create([
        'company_id' => $company->getKey(),
        'warehouse_id' => $destinationWarehouse->getKey(),
        'code' => 'B-01',
        'name' => 'Hedef Raf',
        'is_active' => true,
    ]);

    return [$company, $product, $sourceWarehouse, $source, $destinationWarehouse, $destination];
}

/** @return array{Company, Product} */
function m48ProductContext(string $code): array
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

    return [$company, $product];
}
