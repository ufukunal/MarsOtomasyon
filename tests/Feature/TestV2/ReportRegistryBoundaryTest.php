<?php

use App\Support\Reporting\ReportRegistry;

it('v2 report registry denies unknown report key without executing query', function () {
    config(['reporting.queries' => []]);
    expect(fn () => app(ReportRegistry::class)->query('__missing_report__'))
        ->toThrow(LogicException::class);
});

it('v2 report registry refuses configuration pointing to a class without ReportQuery contract', function () {
    config(['reporting.queries' => [stdClass::class]]);
    expect(fn () => app(ReportRegistry::class)->definitions())
        ->toThrow(LogicException::class);
});
