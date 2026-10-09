<?php

use App\Support\Production\ProductionCostCalculator;

it('v2 production calculates material costs using exact decimal arithmetic', function () {
    $cost = new ProductionCostCalculator;

    expect($cost->materialCost([
        ['quantity' => '1.500', 'unit_cost' => '20.0000'],
        ['quantity' => '2.000', 'unit_cost' => '2.5000'],
    ]))->toBe('35.0000');
});

it('v2 production permits a zero-input completion without a fabricated material cost', function () {
    expect((new ProductionCostCalculator)->materialCost([]))->toBe('0.0000');
});

it('v2 production rejects negative material quantities and unit costs', function (array $rows) {
    expect(fn () => (new ProductionCostCalculator)->materialCost($rows))
        ->toThrow(DomainException::class);
})->with([
    'negative quantity' => [[['quantity' => '-0.001', 'unit_cost' => '1.0000']]],
    'negative price' => [[['quantity' => '1.000', 'unit_cost' => '-0.0001']]],
]);

it('v2 production divides aggregate material and subcontract service costs by output quantity', function () {
    expect((new ProductionCostCalculator)->unitCost('100.0000', '25.0000', '5.000'))
        ->toBe('25.0000');
});

it('v2 production rejects zero or negative output quantities', function (string $quantity) {
    expect(fn () => (new ProductionCostCalculator)->unitCost('12.0000', '0', $quantity))
        ->toThrow(DomainException::class);
})->with(['0.000', '-1.000']);

it('v2 production rejects aggregate negative completion costs', function () {
    expect(fn () => (new ProductionCostCalculator)->unitCost('1.0000', '-2.0000', '1.000'))
        ->toThrow(DomainException::class);
});

it('v2 production computes weighted moving-average cost without float drift', function () {
    expect((new ProductionCostCalculator)->projectedAverage('3.000', '10.0000', '1.000', '20.0000'))
        ->toBe('12.5000');
});

it('v2 production initializes moving-average on empty stock using incoming cost', function () {
    expect((new ProductionCostCalculator)->projectedAverage('0.000', '999.0000', '2.000', '17.4321'))
        ->toBe('17.4321');
});

it('v2 production refuses zero and negative incoming stock', function (string $incoming) {
    expect(fn () => (new ProductionCostCalculator)->projectedAverage('2.000', '4.0000', $incoming, '9.0000'))
        ->toThrow(DomainException::class);
})->with(['0.000', '-1.000']);
