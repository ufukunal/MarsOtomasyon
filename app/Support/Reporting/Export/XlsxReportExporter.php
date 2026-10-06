<?php

namespace App\Support\Reporting\Export;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

final class XlsxReportExporter
{
    public function export(ReportExportDataset $dataset): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rapor');

        foreach ($dataset->columns as $columnIndex => $column) {
            $cell = $sheet->getCell([$columnIndex + 1, 1]);
            $cell->setValueExplicit($column->label, DataType::TYPE_STRING);
        }

        foreach ($dataset->rows as $rowIndex => $row) {
            foreach ($dataset->columns as $columnIndex => $column) {
                $value = $row[$column->key] ?? null;
                $cell = $sheet->getCell([$columnIndex + 1, $rowIndex + 2]);

                if ($value === null || $value === '') {
                    $cell->setValue(null);

                    continue;
                }

                if (in_array($column->type, ['integer', 'decimal', 'money', 'quantity'], true)
                    && is_numeric((string) $value)) {
                    $cell->setValueExplicit((string) $value, DataType::TYPE_NUMERIC);

                    continue;
                }

                if ($column->type === 'boolean') {
                    $cell->setValueExplicit((bool) $value, DataType::TYPE_BOOL);

                    continue;
                }

                $cell->setValueExplicit(
                    is_scalar($value) ? (string) $value : '',
                    DataType::TYPE_STRING,
                );
            }
        }

        $sheet->freezePane('A2');
        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());

        for ($columnIndex = 1; $columnIndex <= count($dataset->columns); $columnIndex++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
        }

        $path = tempnam(sys_get_temp_dir(), 'mars-report-');

        if ($path === false) {
            $spreadsheet->disconnectWorksheets();

            throw new RuntimeException('XLSX geçici dosyası oluşturulamadı.');
        }

        try {
            (new Xlsx($spreadsheet))->save($path);
            $contents = file_get_contents($path);

            if ($contents === false) {
                throw new RuntimeException('XLSX geçici dosyası okunamadı.');
            }

            return $contents;
        } finally {
            $spreadsheet->disconnectWorksheets();

            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}
