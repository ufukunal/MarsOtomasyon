<?php

use App\Support\Reporting\Export\CsvReportExporter;
use App\Support\Reporting\Export\ReportExportDataset;
use App\Support\Reporting\ReportColumnDefinition;

function v2CsvDataset(array $rows, array $columns): ReportExportDataset
{
    return new ReportExportDataset(
        key: 'v2_csv',
        title: 'Security CSV',
        columns: $columns,
        rows: $rows,
        totals: [],
        totalRows: count($rows),
        filters: [],
        sort: [],
        definitionVersion: 1,
    );
}

it('v2 CSV export starts with UTF-8 BOM and preserves declared column order', function () {
    $csv = (new CsvReportExporter)->export(v2CsvDataset(
        [['code' => 'ITEM-1', 'name' => 'Türkçe Ürün']],
        [new ReportColumnDefinition('name', 'Ürün'), new ReportColumnDefinition('code', 'Kod')],
    ));

    expect(str_starts_with($csv, "\xEF\xBB\xBF"))->toBeTrue()
        ->and($csv)->toContain("Ürün;Kod")
        ->toContain('"Türkçe Ürün";ITEM-1');
});

it('v2 CSV export neutralizes formula prefixes in string cells without modifying numeric amounts', function () {
    $columns = [
        new ReportColumnDefinition('description', 'Description'),
        new ReportColumnDefinition('amount', 'Amount', 'money'),
    ];
    $csv = (new CsvReportExporter)->export(v2CsvDataset([
        ['description' => '=HYPERLINK("https://malicious.example")', 'amount' => '-25.50'],
        ['description' => '+SUM(1,1)', 'amount' => '125.00'],
        ['description' => '@CMD', 'amount' => '0.00'],
        ['description' => "\t-foo", 'amount' => '10.50'],
    ], $columns));

    expect($csv)->toContain("'=HYPERLINK")
        ->toContain("'+SUM(1,1)")
        ->toContain("'@CMD")
        ->toContain("'\t-foo")
        ->toContain('-25.50')
        ->not->toContain("'-25.50");
});

it('v2 CSV exporter preserves embedded delimiters and quotes by CSV field encoding', function () {
    $csv = (new CsvReportExporter)->export(v2CsvDataset(
        [['description' => 'A;"Quoted" item']],
        [new ReportColumnDefinition('description', 'Text')],
    ));

    expect($csv)->toContain('"A;""Quoted"" item"');
});

it('v2 CSV exporter leaves null cells empty without stringifying arrays', function () {
    $csv = (new CsvReportExporter)->export(v2CsvDataset(
        [['description' => null], ['description' => ['malformed' => 'array']]],
        [new ReportColumnDefinition('description', 'Text')],
    ));

    expect($csv)->not->toContain('Array')
        ->toContain("Text\n");
});