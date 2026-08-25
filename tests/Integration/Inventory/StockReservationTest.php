<?php

use App\Foundation\Idempotency\IdempotencyConflict;
use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Tax;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Enums\StockReservationStatus;
use App\Modules\Inventory\Ledger\PostStockMovementData;
use App\Modules\Inventory\Ledger\StockMovementPoster;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockReservation;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Inventory\Reservations\StockReservationActionResult;
use App\Modules\Inventory\Reservations\StockReservationService;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

uses(DatabaseMigrations::class);

it('reserves available stock exactly once without creating a stock movement', function (): void {
    [$company, $product, $warehouse, $location] = m45StockContext('M45-RESERVE');
    m45PostOpening($company, $product, $warehouse, $location, '10', '100');
    $identity = m45Identity($company, 'order-100-line-1', 'inventory.reserve');

    $first = DB::transaction(fn (): StockReservationActionResult => app(StockReservationService::class)->reserve(
        $identity,
        (int) $product->getKey(),
        (int) $warehouse->getKey(),
        (int) $location->getKey(),
        '4',
    ));
    $replay = DB::transaction(fn (): StockReservationActionResult => app(StockReservationService::class)->reserve(
        $identity,
        (int) $product->getKey(),
        (int) $warehouse->getKey(),
        (int) $location->getKey(),
        '4.000000',
    ));

    $balance = StockBalance::query()->where('product_id', $product->getKey())->firstOrFail();
    expect($first->replayed)->toBeFalse()
        ->and($replay->replayed)->toBeTrue()
        ->and($first->reservation->getKey())->toBe($replay->reservation->getKey())
        ->and($first->reservation->quantity)->toBe('4.000000')
        ->and($first->reservation->statusEnum())->toBe(StockReservationStatus::Active)
        ->and($balance->reserved_quantity)->toBe('4.000000')
        ->and($balance->available_quantity)->toBe('6.000000')
        ->and(StockReservation::query()->count())->toBe(1)
        ->and(StockMovement::query()->count())->toBe(1);

    expect(fn () => DB::transaction(fn (): StockReservationActionResult => app(StockReservationService::class)->reserve(
        $identity,
        (int) $product->getKey(),
        (int) $warehouse->getKey(),
        (int) $location->getKey(),
        '5',
    )))->toThrow(IdempotencyConflict::class);
});

it('blocks over reservation atomically at service and PostgreSQL boundaries', function (): void {
    [$company, $product, $warehouse, $location] = m45StockContext('M45-CAP');
    m45PostOpening($company, $product, $warehouse, $location, '5', '50');

    DB::transaction(fn (): StockReservationActionResult => app(StockReservationService::class)->reserve(
        m45Identity($company, 'cap-4', 'inventory.reserve'),
        (int) $product->getKey(),
        (int) $warehouse->getKey(),
        (int) $location->getKey(),
        '4',
    ));

    expect(fn () => DB::transaction(fn (): StockReservationActionResult => app(StockReservationService::class)->reserve(
        m45Identity($company, 'cap-over', 'inventory.reserve'),
        (int) $product->getKey(),
        (int) $warehouse->getKey(),
        (int) $location->getKey(),
        '2',
    )))->toThrow(ValidationException::class);

    expect(fn () => DB::table('stock_reservations')->insert(m45RawReservation(
        $company,
        $product,
        $warehouse,
        $location,
        '2.000000',
        'raw-over',
    )))->toThrow(QueryException::class);

    $balance = StockBalance::query()->where('product_id', $product->getKey())->firstOrFail();
    expect($balance->reserved_quantity)->toBe('4.000000')
        ->and($balance->available_quantity)->toBe('1.000000')
        ->and(StockReservation::query()->count())->toBe(1);
});

it('releases reservations and consumes them before the physical stock effect in the same transaction', function (): void {
    [$company, $product, $warehouse, $location] = m45StockContext('M45-LIFECYCLE');
    m45PostOpening($company, $product, $warehouse, $location, '10', '100');

    $released = DB::transaction(function () use ($company, $product, $warehouse, $location): StockReservationActionResult {
        $reserve = app(StockReservationService::class)->reserve(
            m45Identity($company, 'release-reserve', 'inventory.reserve'),
            (int) $product->getKey(),
            (int) $warehouse->getKey(),
            (int) $location->getKey(),
            '4',
        );

        return app(StockReservationService::class)->release(
            m45Identity($company, 'release-effect', 'inventory.release'),
            (int) $reserve->reservation->getKey(),
        );
    });

    $releaseReplay = DB::transaction(fn (): StockReservationActionResult => app(StockReservationService::class)->release(
        m45Identity($company, 'release-effect', 'inventory.release'),
        (int) $released->reservation->getKey(),
    ));

    $balance = StockBalance::query()->where('product_id', $product->getKey())->firstOrFail();
    expect($released->reservation->statusEnum())->toBe(StockReservationStatus::Released)
        ->and($releaseReplay->replayed)->toBeTrue()
        ->and($balance->reserved_quantity)->toBe('0.000000')
        ->and($balance->available_quantity)->toBe('10.000000')
        ->and(StockMovement::query()->count())->toBe(1);

    $consumed = DB::transaction(function () use ($company, $product, $warehouse, $location): StockReservationActionResult {
        $reserve = app(StockReservationService::class)->reserve(
            m45Identity($company, 'consume-reserve', 'inventory.reserve'),
            (int) $product->getKey(),
            (int) $warehouse->getKey(),
            (int) $location->getKey(),
            '3',
        );
        $consume = app(StockReservationService::class)->consume(
            m45Identity($company, 'consume-effect', 'inventory.consume'),
            (int) $reserve->reservation->getKey(),
        );

        app(StockMovementPoster::class)->post(new PostStockMovementData(
            sourceEffect: m45Identity($company, 'shipment-100', 'inventory.adjustment_out'),
            productId: (int) $product->getKey(),
            warehouseId: (int) $warehouse->getKey(),
            locationId: (int) $location->getKey(),
            movementType: StockMovementType::AdjustmentOut,
            quantity: '3',
        ));

        return $consume;
    });

    $balance->refresh();
    expect($consumed->reservation->statusEnum())->toBe(StockReservationStatus::Consumed)
        ->and($balance->quantity)->toBe('7.000000')
        ->and($balance->reserved_quantity)->toBe('0.000000')
        ->and($balance->available_quantity)->toBe('7.000000')
        ->and($balance->inventory_value)->toBe('700.000000')
        ->and(StockMovement::query()->count())->toBe(2);
});

it('serializes competing reservations on the stock balance row and prevents over allocation after the lock clears', function (): void {
    [$company, $product, $warehouse, $location] = m45StockContext('M45-CONCURRENT');
    m45PostOpening($company, $product, $warehouse, $location, '5', '25');

    config(['database.connections.pgsql_concurrent' => config('database.connections.pgsql')]);
    DB::purge('pgsql_concurrent');
    $concurrent = DB::connection('pgsql_concurrent');
    $concurrent->statement("SET lock_timeout TO '150ms'");

    DB::beginTransaction();

    try {
        DB::table('stock_reservations')->insert(m45RawReservation(
            $company,
            $product,
            $warehouse,
            $location,
            '4.000000',
            'lock-holder',
        ));

        expect(fn () => $concurrent->table('stock_reservations')->insert(m45RawReservation(
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
    expect(fn () => $concurrent->table('stock_reservations')->insert(m45RawReservation(
        $company,
        $product,
        $warehouse,
        $location,
        '2.000000',
        'lock-waiter',
    )))->toThrow(QueryException::class);

    $balance = StockBalance::query()->where('product_id', $product->getKey())->firstOrFail();
    expect($balance->reserved_quantity)->toBe('4.000000')
        ->and($balance->available_quantity)->toBe('1.000000')
        ->and(StockReservation::query()->count())->toBe(1);

    DB::disconnect('pgsql_concurrent');
});

it('protects reservation lifecycle integrity and requires the owning business transaction', function (): void {
    [$company, $product, $warehouse, $location] = m45StockContext('M45-GUARDS');
    m45PostOpening($company, $product, $warehouse, $location, '8', '40');
    $service = app(StockReservationService::class);

    expect(fn () => $service->reserve(
        m45Identity($company, 'outside-tx', 'inventory.reserve'),
        (int) $product->getKey(),
        (int) $warehouse->getKey(),
        (int) $location->getKey(),
        '1',
    ))->toThrow(LogicException::class);

    $reservation = DB::transaction(fn (): StockReservationActionResult => $service->reserve(
        m45Identity($company, 'guard-reserve', 'inventory.reserve'),
        (int) $product->getKey(),
        (int) $warehouse->getKey(),
        (int) $location->getKey(),
        '2',
    ))->reservation;

    expect(fn () => DB::table('stock_reservations')->where('id', $reservation->getKey())->update([
        'status' => 'released',
        'released_at' => now(),
    ]))->toThrow(QueryException::class);
    expect(fn () => DB::table('stock_reservations')->where('id', $reservation->getKey())->delete())
        ->toThrow(QueryException::class);

    DB::transaction(fn (): StockReservationActionResult => $service->release(
        m45Identity($company, 'guard-release', 'inventory.release'),
        (int) $reservation->getKey(),
    ));

    expect(fn () => DB::transaction(fn (): StockReservationActionResult => $service->consume(
        m45Identity($company, 'guard-consume-after-release', 'inventory.consume'),
        (int) $reservation->getKey(),
    )))->toThrow(ValidationException::class);

    $balance = StockBalance::query()->where('product_id', $product->getKey())->firstOrFail();
    expect($reservation->refresh()->statusEnum())->toBe(StockReservationStatus::Released)
        ->and($balance->reserved_quantity)->toBe('0.000000')
        ->and($balance->available_quantity)->toBe('8.000000');
});

function m45Identity(Company $company, string $sourceId, string $effectType): SourceEffectIdentity
{
    return new SourceEffectIdentity(
        companyId: (int) $company->getKey(),
        sourceType: 'inventory.test-reservation',
        sourceId: $sourceId,
        effectType: $effectType,
    );
}

function m45PostOpening(
    Company $company,
    Product $product,
    Warehouse $warehouse,
    WarehouseLocation $location,
    string $quantity,
    string $unitCost,
): void {
    DB::transaction(fn () => app(StockMovementPoster::class)->post(new PostStockMovementData(
        sourceEffect: m45Identity($company, 'opening-'.$company->code, 'inventory.opening'),
        productId: (int) $product->getKey(),
        warehouseId: (int) $warehouse->getKey(),
        locationId: (int) $location->getKey(),
        movementType: StockMovementType::OpeningIn,
        quantity: $quantity,
        unitCost: $unitCost,
    )));
}

/** @return array<string, int|string|\DateTimeInterface|null> */
function m45RawReservation(
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
        'reserve_source_type' => 'inventory.test-reservation',
        'reserve_source_id' => $sourceId,
        'reserve_effect_type' => 'inventory.reserve',
        'reserved_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ];
}

/** @return array{Company, Product, Warehouse, WarehouseLocation} */
function m45StockContext(string $code): array
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
