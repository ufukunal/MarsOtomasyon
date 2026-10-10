<?php

use App\Support\Reporting\Export\CsvReportExporter;
use App\Support\Reporting\Export\ReportExportDataset;
use App\Support\Reporting\ReportColumnDefinition;

it('emits UTF-8 BOM and protects spreadsheet-formula text cells', function (): void {
    $dataset = new ReportExportDataset(
        key: 'stock', title: 'Stock',
        columns: [new ReportColumnDefinition('label', 'Label'), new ReportColumnDefinition('qty', 'Qty', 'quantity')],
        rows: [['label' => '=HYPERLINK("evil")', 'qty' => '12.000']],
        totals: [], totalRows: 1, filters: [], sort: [], definitionVersion: 1,
    );
    $csv = (new CsvReportExporter)->export($dataset);
    expect(str_starts_with($csv, "\xEF\xBB\xBF"))->toBeTrue()
        ->and($csv)->toContain('Label;Qty')
        ->toContain("'=HYPERLINK")
        ->toContain('12.000');
});

it('escapes all four leading spreadsheet formula triggers', function (string $unsafe): void {
    $dataset = new ReportExportDataset(
        key: 'r', title: 'R', columns: [new ReportColumnDefinition('name', 'Name')],
        rows: [['name' => $unsafe]], totals: [], totalRows: 1, filters: [], sort: [], definitionVersion: 1,
    );
    expect((new CsvReportExporter)->export($dataset))->toContain("'".$unsafe);
})->with(['=1+2', '+cmd', '-5+4', '@SUM(1)']);
