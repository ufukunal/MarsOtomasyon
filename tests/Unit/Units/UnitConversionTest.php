<?php

use App\Support\Units\UnitConversionResolver;

it('treats conversion to the same unit as identity without accessing a database', function (): void {
    expect((new UnitConversionResolver)->factor(10, 10))->toBe('1.000000')
        ->and((new UnitConversionResolver)->factor(987, 987))->toBe('1.000000');
});
