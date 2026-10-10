<?php

use App\Support\Reporting\ReportRequest;
use App\Support\Reporting\ReportSort;

it('preserves report request page, columns and filters without mutation', function (): void {
    $request = new ReportRequest(filters: ['year' => 2026], columns: ['code'], sort: [new ReportSort('code', 'desc')], limit: 20, offset: 40);
    expect($request->filters)->toBe(['year' => 2026])
        ->and($request->columns)->toBe(['code'])
        ->and($request->sort[0]->direction)->toBe('desc')
        ->and($request->limit)->toBe(20)
        ->and($request->offset)->toBe(40);
});

it('rejects unsupported sort directions', function (): void {
    expect(new ReportSort('date', 'asc')->direction)->toBe('asc');
    expect(fn () => new ReportSort('date', 'DROP TABLE'))->toThrow(DomainException::class);
    expect(fn () => new ReportSort('date', 'ASC'))->toThrow(DomainException::class);
});
