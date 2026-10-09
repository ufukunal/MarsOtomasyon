<?php

use App\Actions\Numbering\GenerateDocumentNumber;
use App\Actions\Periods\EnsurePeriodOpen;
use App\Exceptions\PeriodClosedException;
use App\Exceptions\PeriodReadOnlyException;
use App\Exceptions\PeriodYearMismatchException;
use App\Models\NumberSeries;
use App\Models\PostingPeriod;
use Carbon\CarbonImmutable;

it('v2 document numbering advances monotonically inside the current physical period', function () {
    [$company, $period] = $this->createCompanyWithPeriod('V2NUMB');
    $numbers = app(GenerateDocumentNumber::class);
    $first = $numbers->handle('sales_invoice');
    $second = $numbers->handle('sales_invoice');
    expect($first)->not->toBe($second)
        ->and(NumberSeries::query()->where('document_type', 'sales_invoice')->where('year', 2026)->value('last_number'))
        ->toBe(2);
});

it('v2 document numbering isolates counters across document types', function () {
    $this->createCompanyWithPeriod('V2NUMTYPE');
    $numbers = app(GenerateDocumentNumber::class);
    $numbers->handle('sales_invoice');
    $numbers->handle('purchase_order');
    expect(NumberSeries::query()->where('document_type', 'sales_invoice')->value('last_number'))->toBe(1)
        ->and(NumberSeries::query()->where('document_type', 'purchase_order')->value('last_number'))->toBe(1);
});

it('v2 document numbering rejects a nonactive accounting year', function () {
    $this->createCompanyWithPeriod('V2NUMYEAR');
    expect(fn () => app(GenerateDocumentNumber::class)->handle('sales_invoice', 2025))
        ->toThrow(PeriodYearMismatchException::class);
    expect(NumberSeries::query()->count())->toBe(0);
});

it('v2 posting refuses a document date in a different fiscal year', function () {
    $this->createCompanyWithPeriod('V2DATEYEAR');
    expect(fn () => app(EnsurePeriodOpen::class)->handle(CarbonImmutable::parse('2025-09-01')))
        ->toThrow(PeriodYearMismatchException::class);
});

it('v2 closed month forbids fiscal posting even when the year is active', function () {
    $this->createCompanyWithPeriod('V2CLOSEMONTH');
    PostingPeriod::query()->create(['year' => 2026, 'month' => 9, 'status' => 'closed']);
    expect(fn () => app(EnsurePeriodOpen::class)->handle(CarbonImmutable::parse('2026-09-01')))
        ->toThrow(PeriodClosedException::class);
});

it('v2 closed fiscal year rejects subsequent number creation without advancing sequence', function () {
    [$company, $period] = $this->createCompanyWithPeriod('V2NUMCLOSED');
    $period->update(['status' => 'closed']);
    expect(fn () => app(GenerateDocumentNumber::class)->handle('sales_invoice'))
        ->toThrow(PeriodReadOnlyException::class);
    expect(NumberSeries::query()->count())->toBe(0);
});
