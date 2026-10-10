<?php

use App\Support\Reporting\MultiPeriod\MultiPeriodQuery;
use App\Support\Reporting\ReportResult;
use App\Support\Reporting\ReportTotalDefinition;

function v4ReportWithTotals(array $totals): ReportResult
{
    return new ReportResult(
        key: 'v4.totals',
        title: 'V4 totals',
        category: 'finance',
        columns: [],
        drillDowns: [],
        rows: [],
        totals: $totals,
        totalRows: 0,
        filters: [],
        sort: [],
        limit: 10,
        offset: 0,
        definitionVersion: 1,
    );
}

function v4MergeMultiPeriodTotals(array &$combined, ReportResult $report, array $definitions): void
{
    $query = (new ReflectionClass(MultiPeriodQuery::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod(MultiPeriodQuery::class, 'mergeTotals');
    $args = [&$combined, $report, $definitions];
    $method->invokeArgs($query, $args);
}

it('preserves decimal precision and integer counts across successive reporting periods', function (): void {
    $combined = [];
    $definitions = [
        'net' => new ReportTotalDefinition('net', 'Net'),
        'count' => new ReportTotalDefinition('count', 'Count', 'integer'),
    ];

    v4MergeMultiPeriodTotals($combined, v4ReportWithTotals(['net' => '1.10000001', 'count' => '2']), $definitions);
    v4MergeMultiPeriodTotals($combined, v4ReportWithTotals(['net' => '2.00000002', 'count' => '4']), $definitions);

    expect($combined['net'])->toBe('3.10000003')
        ->and($combined['count'])->toBe('6');
});

it('rejects nonnumeric reporting totals rather than silently corrupting the aggregate', function (string $value): void {
    $combined = ['amount' => '10'];

    expect(fn () => v4MergeMultiPeriodTotals(
        $combined,
        v4ReportWithTotals(['amount' => $value]),
        ['amount' => new ReportTotalDefinition('amount', 'Amount')],
    ))->toThrow(DomainException::class);
})->with(['invalid', '', '1;DELETE', 'not-a-number']);
