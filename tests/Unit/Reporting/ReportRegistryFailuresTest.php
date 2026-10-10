<?php

use App\Support\Reporting\ReportRegistry;

it('rejects an unregistered reporting key rather than executing an arbitrary query', function (): void {
    $old = config('reporting.queries');

    try {
        config(['reporting.queries' => []]);
        $registry = new ReportRegistry(app());

        expect(fn () => $registry->query('production.nonexistent'))
            ->toThrow(LogicException::class);
    } finally {
        config(['reporting.queries' => $old]);
    }
});

it('rejects non-report-query classes in reporting configuration', function (): void {
    $old = config('reporting.queries');

    try {
        config(['reporting.queries' => [stdClass::class]]);
        $registry = new ReportRegistry(app());

        expect(fn () => $registry->definitions())
            ->toThrow(LogicException::class);
    } finally {
        config(['reporting.queries' => $old]);
    }
});
