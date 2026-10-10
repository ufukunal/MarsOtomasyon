<?php

use App\Support\Reporting\ReportColumnDefinition;

it('defaults to ordinary sortable non-cost-sensitive columns', function (): void {
    $column = new ReportColumnDefinition('reference', 'Reference');
    expect($column->key)->toBe('reference')
        ->and($column->sortable)->toBeTrue()
        ->and($column->costSensitive)->toBeFalse()
        ->and($column->defaultVisible)->toBeTrue();
});

it('retains explicit cost-sensitive metadata for permission-aware report engines', function (): void {
    $column = new ReportColumnDefinition('unit_cost', 'Cost', 'money', true, true, false);
    expect($column->costSensitive)->toBeTrue()
        ->and($column->defaultVisible)->toBeFalse()
        ->and($column->type)->toBe('money');
});
