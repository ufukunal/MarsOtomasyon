<?php

use App\Models\Period\StockBalance;

it('deducts ordinary, consignment and quarantine allocations from available stock', function (): void {
    $balance = new StockBalance([
        'quantity' => '25',
        'reserved' => '3',
        'consignment_reserved' => '2',
        'quarantine' => '4',
    ]);
    expect($balance->available())->toBe('16.000')
        ->and($balance->available)->toBe('16.000');
});

it('does not hide negative availability or over-reservation', function (): void {
    $balance = new StockBalance([
        'quantity' => '1',
        'reserved' => '2',
        'consignment_reserved' => '0',
        'quarantine' => '0',
    ]);
    expect($balance->available())->toBe('-1.000');
});
