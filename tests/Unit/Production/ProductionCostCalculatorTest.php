<?php

use App\Support\Production\ProductionCostCalculator;

it('sums material costs using fixed scale arithmetic', function (): void {
    $calc = new ProductionCostCalculator;
    expect($calc->materialCost([
        ['quantity' => '2', 'unit_cost' => '4.50'],
        ['quantity' => '3', 'unit_cost' => '2.00'],
    ]))->toBe('15.0000')
        ->and($calc->materialCost([]))->toBe('0.0000');
});

it('computes unit and weighted average cost', function (): void {
    $calc = new ProductionCostCalculator;
    expect($calc->unitCost('90', '10', '4'))->toBe('25.0000')
        ->and($calc->projectedAverage('10', '3', '10', '5'))->toBe('4.0000')
        ->and($calc->projectedAverage('0', '0', '3', '7.25'))->toBe('7.2500');
});

it('rejects negative material inputs, zero completion and zero incoming quantity', function (): void {
    $calc = new ProductionCostCalculator;
    expect(fn () => $calc->materialCost([['quantity' => '-1', 'unit_cost' => '2']]))->toThrow(DomainException::class);
    expect(fn () => $calc->materialCost([['quantity' => '1', 'unit_cost' => '-2']]))->toThrow(DomainException::class);
    expect(fn () => $calc->unitCost('1', '0', '0'))->toThrow(DomainException::class);
    expect(fn () => $calc->projectedAverage('3', '2', '0', '1'))->toThrow(DomainException::class);
});
