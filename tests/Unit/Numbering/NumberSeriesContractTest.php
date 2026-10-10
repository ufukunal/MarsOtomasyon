<?php

use App\Models\NumberSeries;
use App\Models\Period\Document;
use App\Models\Period\StockBalance;

it('uses a master-scoped number series with period-scoped documents and balances', function (): void {
    expect((new NumberSeries)->getConnectionName())->toBe('master');
    expect((new Document)->getTable())->toBe('documents');
    expect((new StockBalance)->getTable())->toBe('stock_balances');
});
