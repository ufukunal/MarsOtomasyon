<?php

use App\Models\Period\ImportFile;
use App\Support\Imports\ImportCostAllocator;

it('blocks import landed-cost allocation before database access when the exchange rate is invalid', function (string $rate): void {
    $file = new ImportFile;
    $file->exchange_rate = $rate;
    expect(fn () => (new ImportCostAllocator)->recalculate($file))->toThrow(DomainException::class);
})->with(['0', '-1', '-0.000001']);
