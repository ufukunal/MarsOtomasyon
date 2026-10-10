<?php

use App\Exceptions\NoActivePeriodException;
use App\Models\NumberSeries;
use App\Models\Period\Document;
use App\Models\Period\StockBalance;
use App\Support\Period\PeriodContext;

it('scopes document numbering and stock tables to the selected period', function (): void {
    PeriodContext::clear();
    expect((new NumberSeries)->getTable())->toBe('number_series')
        ->and((new Document)->getTable())->toBe('documents')
        ->and((new StockBalance)->getTable())->toBe('stock_balances');

    expect(fn () => (new NumberSeries)->getConnectionName())->toThrow(NoActivePeriodException::class);
    expect(fn () => (new Document)->getConnectionName())->toThrow(NoActivePeriodException::class);
});
