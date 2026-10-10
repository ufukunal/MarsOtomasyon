<?php

use App\Livewire\Components\DataTable\Column;
use App\Support\Formatting\TableValueFormatter as Format;
use Carbon\CarbonImmutable;

it('formats table booleans, dates, money, quantity and null values', function (): void {
    $plain = Column::make('v', 'Value');
    expect(Format::format($plain, true))->toBe('Evet')
        ->and(Format::format($plain, false))->toBe('Hayır')
        ->and(Format::format($plain, null))->toBe('')
        ->and(Format::format($plain, CarbonImmutable::parse('2026-10-10')))->toBe('10.10.2026')
        ->and(Format::format(Column::make('price', 'Price')->money(), '1234.5'))->toBe('1.234,50')
        ->and(Format::format(Column::make('qty', 'Qty')->quantity(), '12.25'))->toBe('12,250');
});
