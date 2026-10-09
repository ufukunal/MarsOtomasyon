<?php

use App\Support\Production\ProductionCostCalculator;

it('v2 production cost sums multiple quantities using exact decimals', function () {
    $cost = (new ProductionCostCalculator)->materialCost([
        ['quantity' => '2.000', 'unit_cost' => '5.0125'],
        ['quantity' => '3.000', 'unit_cost' => '1.2500'],
    ]);
    expect($cost)->toBe('13.7750');
});

it('v2 production cost returns zero for an empty consumption list', function () {
    expect((new ProductionCostCalculator)->materialCost([]))->toBe('0.0000');
});

it('v2 production cost refuses negative material quantity and unit cost', function (array $row) {
    expect(fn () => (new ProductionCostCalculator)->materialCost([$row]))
        ->toThrow(DomainException::class);
})->with([
    [['quantity' => '-1.000', 'unit_cost' => '5.0000']],
    [['quantity' => '1.000', 'unit_cost' => '-5.0000']],
]);

it('v2 production completion refuses nonpositive output quantity', function (string $quantity) {
    expect(fn () => (new ProductionCostCalculator)->unitCost('10.0000', '5.0000', $quantity))
        ->toThrow(DomainException::class);
})->with(['0.000', '-1.000']);

it('v2 production unit cost distributes service plus raw material cost', function () {
    expect((new ProductionCostCalculator)->unitCost('25.0000', '15.0000', '8.000'))
        ->toBe('5.0000');
});

it('v2 production moving average uses weighted cost when inventory exists', function () {
    expect((new ProductionCostCalculator)->projectedAverage('10.000', '2.0000', '10.000', '4.0000'))
        ->toBe('3.0000');
});

it('v2 production moving average uses incoming unit cost when inventory is empty', function () {
    expect((new ProductionCostCalculator)->projectedAverage('0.000', '99.0000', '1.000', '4.3210'))
        ->toBe('4.3210');
});

it('v2 production average refuses incoming quantity of zero', function () {
    expect(fn () => (new ProductionCostCalculator)->projectedAverage('1.000', '2.0000', '0.000', '1.0000'))
        ->toThrow(DomainException::class);
});