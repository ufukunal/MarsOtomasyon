<?php

use App\Support\Reporting\MultiPeriod\MultiPeriodQuery;
use App\Support\Reporting\ReportColumnDefinition;
use App\Support\Reporting\ReportSort;

function v4SortConsolidatedRows(array &$rows, array $columns, array $sort): void
{
    $query = (new ReflectionClass(MultiPeriodQuery::class))->newInstanceWithoutConstructor();
    $params = [&$rows, $columns, $sort];

    (new ReflectionMethod(MultiPeriodQuery::class, 'sortRows'))->invokeArgs($query, $params);
}

it('compares monetary quantities numerically rather than lexicographically across periods', function (): void {
    $rows = [
        ['id' => 7, 'period_year' => 2025, 'amount' => '100.0000'],
        ['id' => 2, 'period_year' => 2026, 'amount' => '9.5000'],
        ['id' => 8, 'period_year' => 2026, 'amount' => '20.0000'],
    ];

    v4SortConsolidatedRows(
        $rows,
        [new ReportColumnDefinition('amount', 'Amount', 'money')],
        [new ReportSort('amount', 'asc')],
    );

    expect(array_column($rows, 'amount'))->toBe(['9.5000', '20.0000', '100.0000']);
});

it('uses period year and record ID as deterministic tie-breakers for equal sort values', function (): void {
    $rows = [
        ['id' => 10, 'period_year' => 2026, 'status' => 'posted'],
        ['id' => 2, 'period_year' => 2025, 'status' => 'posted'],
        ['id' => 5, 'period_year' => 2025, 'status' => 'posted'],
    ];

    v4SortConsolidatedRows(
        $rows,
        [new ReportColumnDefinition('status', 'Status')],
        [new ReportSort('status', 'asc')],
    );

    expect(array_column($rows, 'id'))->toBe([2, 5, 10]);
});

it('sorts missing and empty values after present report values', function (): void {
    $rows = [
        ['id' => 1, 'period_year' => 2026, 'amount' => null],
        ['id' => 2, 'period_year' => 2026, 'amount' => '0'],
        ['id' => 3, 'period_year' => 2026, 'amount' => ''],
        ['id' => 4, 'period_year' => 2026, 'amount' => '-2'],
    ];

    v4SortConsolidatedRows(
        $rows,
        [new ReportColumnDefinition('amount', 'Amount', 'decimal')],
        [new ReportSort('amount')],
    );

    expect(array_slice(array_column($rows, 'id'), 0, 2))->toBe([4, 2])
        ->and(array_slice(array_column($rows, 'id'), -2))->toEqualCanonicalizing([1, 3]);
});
