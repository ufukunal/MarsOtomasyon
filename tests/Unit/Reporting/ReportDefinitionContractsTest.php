<?php

use App\Support\Reporting\ReportColumnDefinition;
use App\Support\Reporting\ReportDefinition;
use App\Support\Reporting\ReportSort;

function marsReportDefinition(array $columns, array $sort = [], array $exporters = ['screen', 'csv']): ReportDefinition
{
    return new ReportDefinition(
        key: 'v4-test', title: 'Test Report', category: 'inventory',
        permission: 'reports.view', filters: [], columns: $columns,
        defaultSort: $sort, exporters: $exporters,
    );
}

it('hides cost-sensitive columns when a user lacks cost viewing permissions', function (): void {
    $report = marsReportDefinition([
        new ReportColumnDefinition('sku', 'SKU'),
        new ReportColumnDefinition('cost', 'Cost', 'money', true, true, true),
    ]);

    expect($report->defaultColumnKeys(false))->toBe(['sku'])
        ->and($report->defaultColumnKeys(true))->toBe(['sku', 'cost']);
    expect($report->columnMap())->toHaveKeys(['sku', 'cost']);
});

it('rejects duplicate and invalid report schema keys', function (): void {
    expect(fn () => marsReportDefinition([
        new ReportColumnDefinition('sku', 'SKU'),
        new ReportColumnDefinition('sku', 'Duplicate SKU'),
    ]))->toThrow(LogicException::class);

    expect(fn () => marsReportDefinition([
        new ReportColumnDefinition('sku', 'SKU'),
    ], [new ReportSort('missing', 'asc')]))->toThrow(LogicException::class);

    expect(fn () => marsReportDefinition([
        new ReportColumnDefinition('sku', 'SKU'),
    ], [], ['screen', 'shell']))->toThrow(LogicException::class);
});
