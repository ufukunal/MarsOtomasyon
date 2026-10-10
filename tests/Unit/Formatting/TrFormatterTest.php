<?php

use App\Support\Formatting\TrFormatter;
use Carbon\CarbonImmutable;

it('formats money and quantity with Turkish grouping and separators', function (): void {
    expect(TrFormatter::money('1234567.50'))->toBe('1.234.567,50')
        ->and(TrFormatter::money('1250.12', 0))->toBe('1.250')
        ->and(TrFormatter::quantity('1234.5'))->toBe('1.234,500')
        ->and(TrFormatter::money(0))->toBe('0,00');
});

it('renders dates and absent dates correctly', function (): void {
    expect(TrFormatter::date(CarbonImmutable::parse('2026-10-10')))->toBe('10.10.2026')
        ->and(TrFormatter::date(null))->toBe('');
});
