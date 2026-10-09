<?php

use App\Support\Reporting\ReportFilterDefinition;
use App\Support\Reporting\ReportSort;

it('v2 reporting trims text but rejects empty oversized or non-scalar filters', function () {
    $filter = new ReportFilterDefinition('name', 'Name');
    expect($filter->normalize('  Test  '))->toBe('Test');

    foreach ([str_repeat('x', 256), [], '   '] as $value) {
        expect(fn () => $filter->normalize($value))->toThrow(DomainException::class);
    }
});

it('v2 reporting rejects missing required filters', function () {
    $filter = new ReportFilterDefinition('period', 'Period', required: true);

    foreach ([null, ''] as $value) {
        expect(fn () => $filter->normalize($value))->toThrow(DomainException::class);
    }
});

it('v2 reporting permits explicitly optional null filters', function () {
    expect((new ReportFilterDefinition('keyword', 'Keyword'))->normalize(null))->toBeNull();
});

it('v2 reporting checks leap days rather than just date format', function () {
    $filter = new ReportFilterDefinition('date_from', 'Date', 'date');

    expect($filter->normalize('2024-02-29'))->toBe('2024-02-29');

    foreach (['2025-02-29', '2024-13-01', '2024-01-99', '2024/01/01'] as $invalid) {
        expect(fn () => $filter->normalize($invalid))->toThrow(DomainException::class);
    }
});

it('v2 reporting accepts signed integers and rejects fractional or injection values', function () {
    $filter = new ReportFilterDefinition('offset', 'Offset', 'integer');

    expect($filter->normalize('-2'))->toBe(-2)
        ->and($filter->normalize(0))->toBe(0);

    foreach (['1.5', '1 OR 1=1', '0x10', true] as $invalid) {
        expect(fn () => $filter->normalize($invalid))->toThrow(DomainException::class);
    }
});

it('v2 reporting requires strictly positive identifiers', function () {
    $filter = new ReportFilterDefinition('product_id', 'Product', 'positive_integer');

    expect($filter->normalize('3'))->toBe(3);

    foreach (['0', '-1', '3.1', []] as $invalid) {
        expect(fn () => $filter->normalize($invalid))->toThrow(DomainException::class);
    }
});

it('v2 reporting preserves decimal precision and rejects localized or SQL-like values', function () {
    $filter = new ReportFilterDefinition('amount', 'Amount', 'decimal');

    expect($filter->normalize('123456789.0001'))->toBe('123456789.0001');

    foreach (['1,2', '1e3', '0;DROP TABLE users', false] as $invalid) {
        expect(fn () => $filter->normalize($invalid))->toThrow(DomainException::class);
    }
});

it('v2 reporting converts boolean filter values consistently', function (mixed $value, bool $result) {
    expect((new ReportFilterDefinition('active', 'Active', 'boolean'))->normalize($value))->toBe($result);
})->with([
    'true literal' => [true, true],
    'false literal' => [false, false],
    'true string' => ['true', true],
    'false string' => ['false', false],
    'one' => ['1', true],
    'zero' => [0, false],
]);

it('v2 reporting rejects ambiguous boolean filters', function () {
    $filter = new ReportFilterDefinition('active', 'Active', 'boolean');

    foreach (['yes', 'on', '2', [], 2] as $invalid) {
        expect(fn () => $filter->normalize($invalid))->toThrow(DomainException::class);
    }
});

it('v2 reporting returns configured select value types and rejects non-allowlisted values', function () {
    $filter = new ReportFilterDefinition('status', 'Status', 'select', allowedValues: ['draft', 'posted', 42]);

    expect($filter->normalize('draft'))->toBe('draft')
        ->and($filter->normalize('42'))->toBe(42);

    foreach (['deleted', '42 OR 1=1', []] as $invalid) {
        expect(fn () => $filter->normalize($invalid))->toThrow(DomainException::class);
    }
});

it('v2 reporting refuses unsupported filter and sort directions', function () {
    expect(fn () => (new ReportFilterDefinition('field', 'Field', 'unknown'))->normalize('value'))
        ->toThrow(DomainException::class);
    expect(fn () => new ReportSort('amount', 'ASC; DROP TABLE documents'))
        ->toThrow(DomainException::class);
    expect((new ReportSort('amount', 'desc'))->direction)->toBe('desc');
});
