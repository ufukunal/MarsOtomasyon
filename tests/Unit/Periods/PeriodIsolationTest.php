<?php

use App\Exceptions\NoActivePeriodException;
use App\Models\Period\Product;
use App\Support\Period\PeriodContext;

it('fails closed when a period-scoped model is used without period context', function (): void {
    PeriodContext::clear();
    expect(fn () => (new Product)->getConnectionName())->toThrow(NoActivePeriodException::class);
    expect(config('database.connections.period.database'))->toBeNull();
});
