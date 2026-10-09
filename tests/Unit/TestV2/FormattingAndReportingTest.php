<?php

use App\Support\Formatting\TrFormatter;
use App\Support\Reporting\ReportRequest;
use App\Support\Reporting\ReportSort;
use Carbon\CarbonImmutable;

it('v2 formatting preserves grouped Turkish money and negative values', function () {
    expect(TrFormatter::money('1234567.8912'))->toBe('1.234.567,89')
        ->and(TrFormatter::money('-1234.5000'))->toBe('-1.234,50')
        ->and(TrFormatter::money('0'))->toBe('0,00');
});

it('v2 formatting supports money display scales without binary floating point', function () {
    expect(TrFormatter::money('10.9876', 4))->toBe('10,9876')
        ->and(TrFormatter::money('10.9876', 0))->toBe('10');
});

it('v2 formatting prints quantities in base unit precision', function () {
    expect(TrFormatter::quantity('12345.678'))->toBe('12.345,678')
        ->and(TrFormatter::quantity('-1.250'))->toBe('-1,250');
});

it('v2 formatting handles optional dates without a current-time fallback', function () {
    expect(TrFormatter::date(null))->toBe('')
        ->and(TrFormatter::date(CarbonImmutable::parse('2024-02-29')))->toBe('29.02.2024');
});

it('v2 report request retains filters paging selected columns and sorting', function () {
    $request = new ReportRequest(
        filters: ['currency' => 'TRY'],
        columns: ['number', 'grand_total'],
        sort: [['key' => 'number', 'direction' => 'desc']],
        limit: 30,
        offset: 60,
    );

    expect($request->filters)->toBe(['currency' => 'TRY'])
        ->and($request->columns)->toBe(['number', 'grand_total'])
        ->and($request->sort)->toBe([['key' => 'number', 'direction' => 'desc']])
        ->and($request->limit)->toBe(30)
        ->and($request->offset)->toBe(60);
});

it('v2 report sorts require canonical directions', function () {
    expect((new ReportSort('date'))->direction)->toBe('asc')
        ->and((new ReportSort('date', 'desc'))->direction)->toBe('desc');

    expect(fn () => new ReportSort('date', 'DESC'))
        ->toThrow(DomainException::class);
});
