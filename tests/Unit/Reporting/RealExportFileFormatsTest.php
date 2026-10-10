<?php

use App\Support\Reporting\Export\CsvReportExporter;
use App\Support\Reporting\Export\ReportExportDataset;
use App\Support\Reporting\Export\XlsxReportExporter;
use App\Support\Reporting\ReportColumnDefinition;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

function marsV4ExportDataset(): ReportExportDataset
{
    return new ReportExportDataset(
        key: 'v4.export',
        title: 'V4 Export',
        columns: [
            new ReportColumnDefinition('sku', 'SKU'),
            new ReportColumnDefinition('price', 'Price', 'money'),
            new ReportColumnDefinition('memo', 'Memo'),
        ],
        rows: [
            ['sku' => 'SKU-1', 'price' => '12.5000', 'memo' => '=1+2'],
            ['sku' => 'SKU-2', 'price' => '0.0000', 'memo' => '@SUM(A1:A2)'],
        ],
        totals: ['price' => '12.5000'],
        totalRows: 2,
        filters: [],
        sort: [],
        definitionVersion: 1,
    );
}

it('writes a UTF-8 BOM CSV file, preserving decimal values while neutralizing spreadsheet formulas', function (): void {
    $csv = (new CsvReportExporter)->export(marsV4ExportDataset());
    expect(str_starts_with($csv, "\xEF\xBB\xBF"))->toBeTrue()
        ->and($csv)->toContain('SKU;Price;Memo')
        ->and($csv)->toContain('12.5000')
        ->and($csv)->toContain("'=1+2")
        ->and($csv)->toContain("'@SUM(A1:A2)");
});

it('creates an actual XLSX workbook with numeric pricing and literal non-executable formula text', function (): void {
    $data = (new XlsxReportExporter)->export(marsV4ExportDataset());
    $tmp = tempnam(sys_get_temp_dir(), 'mars-v4-xlsx-');

    if ($tmp === false) {
        throw new RuntimeException('Could not create an isolated XLSX fixture.');
    }

    try {
        file_put_contents($tmp, $data);
        $book = IOFactory::load($tmp);
        $sheet = $book->getActiveSheet();

        expect($sheet->getCell('A1')->getValue())->toBe('SKU')
            ->and($sheet->getCell('A2')->getValue())->toBe('SKU-1')
            ->and((float) $sheet->getCell('B2')->getValue())->toBe(12.5)
            ->and($sheet->getCell('C2')->getValue())->toBe('=1+2')
            ->and($sheet->getCell('C2')->getDataType())->toBe('s');
        $book->disconnectWorksheets();
    } finally {
        unlink($tmp);
    }
});

it('escapes untrusted report data in the HTML passed to the PDF renderer', function (): void {
    $dataset = marsV4ExportDataset();
    $untrusted = new ReportExportDataset(
        key: $dataset->key,
        title: '<script>alert(1)</script>',
        columns: $dataset->columns,
        rows: [['sku' => '<img src=x onerror=alert(1)>', 'price' => '0', 'memo' => 'safe']],
        totals: [],
        totalRows: 1,
        filters: [],
        sort: [],
        definitionVersion: 1,
    );

    $html = view('reporting.exports.pdf', [
        'dataset' => $untrusted,
        'generatedAt' => now(),
    ])->render();

    expect($html)->not->toContain('<script>alert(1)</script>')
        ->not->toContain('<img src=x onerror=alert(1)>')
        ->toContain('&lt;script&gt;')
        ->toContain('&lt;img');
});
