<?php

namespace App\Support\Reporting\Export;

use RuntimeException;

final class CsvReportExporter
{
    public function export(ReportExportDataset $dataset): string
    {
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            throw new RuntimeException('CSV export buffer açılamadı.');
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv(
            $stream,
            array_map(fn ($column): string => $column->label, $dataset->columns),
            ';',
            '"',
            '',
        );

        foreach ($dataset->rows as $row) {
            $values = [];

            foreach ($dataset->columns as $column) {
                $value = $row[$column->key] ?? null;
                $values[] = is_scalar($value) || $value === null ? $value : '';
            }

            fputcsv($stream, $values, ';', '"', '');
        }

        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        if ($contents === false) {
            throw new RuntimeException('CSV export buffer okunamadı.');
        }

        return $contents;
    }
}
