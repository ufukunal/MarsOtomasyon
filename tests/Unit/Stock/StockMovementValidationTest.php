<?php

use App\Actions\Stock\RecordStockMovement;
use App\DataObjects\StockMovementData;

function marsStockMovement(array $override = []): StockMovementData
{
    return new StockMovementData(...array_replace([
        'productId' => 1,
        'locationId' => 1,
        'movementDate' => '2026-10-10',
        'direction' => 'in',
        'reason' => 'opening',
        'quantity' => '2.000',
        'unitCost' => '10.0000',
        'updatesAverage' => true,
    ], $override));
}

function marsValidateStockMovement(StockMovementData $movement): void
{
    $action = (new ReflectionClass(RecordStockMovement::class))->newInstanceWithoutConstructor();
    (new ReflectionMethod(RecordStockMovement::class, 'assertData'))->invoke($action, $movement);
}

it('accepts a positive receipt and an outbound movement with no average update', function (): void {
    marsValidateStockMovement(marsStockMovement());
    marsValidateStockMovement(marsStockMovement(['direction' => 'out', 'updatesAverage' => false]));
    expect(true)->toBeTrue();
});

it('rejects malformed direction, quantity, cost and moving average intent', function (array $override): void {
    expect(fn () => marsValidateStockMovement(marsStockMovement($override)))->toThrow(DomainException::class);
})->with([
    'unknown direction' => [['direction' => 'sideways']],
    'zero quantity' => [['quantity' => '0']],
    'negative quantity' => [['quantity' => '-1.000']],
    'negative unit cost' => [['unitCost' => '-0.0001']],
    'outward cost update' => [['direction' => 'out', 'updatesAverage' => true]],
]);
