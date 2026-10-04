<?php

use App\Enums\LocationKind;
use App\Livewire\Components\DataTable\Column;
use App\Support\Formatting\TableValueFormatter;
use Carbon\CarbonImmutable;

it('DataTable değerlerini enum tarih bool null ve money için normalize eder', function () {
    expect(TableValueFormatter::format(Column::make('kind', 'Tip'), LocationKind::Warehouse))
        ->toBe(LocationKind::Warehouse->value)
        ->and(TableValueFormatter::format(Column::make('date', 'Tarih'), CarbonImmutable::parse('2026-10-03')))
        ->toBe('03.10.2026')
        ->and(TableValueFormatter::format(Column::make('active', 'Aktif'), true))
        ->toBe('Evet')
        ->and(TableValueFormatter::format(Column::make('null', 'Boş'), null))
        ->toBe('')
        ->and(TableValueFormatter::format(Column::make('amount', 'Tutar')->money(), '1234.5000'))
        ->toContain('1.234');
});
