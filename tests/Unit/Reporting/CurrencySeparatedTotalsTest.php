<?php

use App\Support\Reporting\CurrencySeparatedTotals;

it('never combines nominal values from different currencies', function (): void {
    $rows = [
        (object) ['currency' => 'USD', 'grand_total' => '100.0000', 'balance' => '-10.0000'],
        (object) ['currency' => 'TRY', 'grand_total' => '100.0000', 'balance' => '50.0000'],
    ];

    expect(CurrencySeparatedTotals::fromGroups($rows, ['grand_total', 'balance']))
        ->toBe([
            'grand_total' => '100.0000 TRY / 100.0000 USD',
            'balance' => '50.0000 TRY / -10.0000 USD',
        ])
        ->and(CurrencySeparatedTotals::fromGroups($rows, ['grand_total'], 'TRY'))
        ->toBe(['grand_total' => '100.0000'])
        ->and(CurrencySeparatedTotals::fromGroups([], ['balance'], 'TRY'))
        ->toBe(['balance' => '0']);
});

it('rejects corrupt or missing currencies instead of producing misleading totals', function (): void {
    expect(fn () => CurrencySeparatedTotals::fromGroups(
        [(object) ['currency' => '', 'balance' => 9000]], ['balance']
    ))->toThrow(\DomainException::class);
});
