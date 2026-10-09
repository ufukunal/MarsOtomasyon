<?php

use App\Actions\Stock\RecordStockMovement;
use App\DataObjects\StockMovementData;
use App\Exceptions\NegativeStockException;
use App\Models\Period\Location;
use App\Models\Period\StockBalance;
use App\Models\Period\StockMovement;

beforeEach(function () {
    $this->createCompanyWithPeriod('V2STKEXP');
});

function v2StockWarehouse(): Location
{
    return Location::query()->create([
        'code' => 'V2-EXP-WH', 'name' => 'Expanded stock warehouse',
        'kind' => 'warehouse', 'is_active' => true, 'is_default' => false,
    ]);
}

it('v2 stock records precise inbound and outbound ledger and physical balance', function () {
    $product = $this->createTestProduct();
    $warehouse = v2StockWarehouse();
    $service = app(RecordStockMovement::class);
    $entry = $service->handle(new StockMovementData(
        productId: $product->id, locationId: $warehouse->id, movementDate: '2026-09-01',
        direction: 'in', reason: 'purchase', quantity: '10.000', unitCost: '2.5000', updatesAverage: true,
    ));
    $exit = $service->handle(new StockMovementData(
        productId: $product->id, locationId: $warehouse->id, movementDate: '2026-09-02',
        direction: 'out', reason: 'sale', quantity: '3.000',
    ));
    expect((string) $entry->balance_after)->toBe('10.000')
        ->and((string) $exit->balance_after)->toBe('7.000')
        ->and((string) $exit->total_cost)->toBe('7.5000')
        ->and((string) StockBalance::query()->where('product_id', $product->id)->where('location_id', $warehouse->id)->value('quantity'))->toBe('7.000')
        ->and(StockMovement::query()->where('product_id', $product->id)->count())->toBe(2);
});

it('v2 stock rejects overdraw and leaves ledger without a partial outgoing event', function () {
    $product = $this->createTestProduct();
    $warehouse = v2StockWarehouse();
    expect(fn () => app(RecordStockMovement::class)->handle(new StockMovementData(
        productId: $product->id, locationId: $warehouse->id, movementDate: '2026-09-02',
        direction: 'out', reason: 'sale', quantity: '1.000',
    )))->toThrow(NegativeStockException::class);
    expect(StockMovement::query()->where('product_id', $product->id)->count())->toBe(0);
});

it('v2 stock rejects invalid direction before touching the ledger', function () {
    $product = $this->createTestProduct();
    $warehouse = v2StockWarehouse();
    expect(fn () => app(RecordStockMovement::class)->handle(new StockMovementData(
        productId: $product->id, locationId: $warehouse->id, movementDate: '2026-09-02',
        direction: 'transfer', reason: 'purchase', quantity: '1.000',
    )))->toThrow(DomainException::class);
    expect(StockMovement::query()->count())->toBe(0);
});

it('v2 stock rejects nonpositive movement amount without any ledger entry', function (string $quantity) {
    $product = $this->createTestProduct();
    $warehouse = v2StockWarehouse();
    expect(fn () => app(RecordStockMovement::class)->handle(new StockMovementData(
        productId: $product->id, locationId: $warehouse->id, movementDate: '2026-09-02',
        direction: 'in', reason: 'purchase', quantity: $quantity,
    )))->toThrow(DomainException::class);
    expect(StockMovement::query()->count())->toBe(0);
})->with(['0.000', '-0.001']);

it('v2 stock rejects updating moving average on outbound movements', function () {
    $product = $this->createTestProduct();
    $warehouse = v2StockWarehouse();
    expect(fn () => app(RecordStockMovement::class)->handle(new StockMovementData(
        productId: $product->id, locationId: $warehouse->id, movementDate: '2026-09-02',
        direction: 'out', reason: 'sale', quantity: '1.000', unitCost: '2.0000', updatesAverage: true,
    )))->toThrow(DomainException::class);
    expect(StockMovement::query()->count())->toBe(0);
});
