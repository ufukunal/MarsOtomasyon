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
                $values[] = $this->safeValue($value, (string) $column->type);
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

    private function safeValue(mixed $value, string $type): int|float|string|null
    {
        if ($value === null || ! is_scalar($value)) {
            return null;
        }

        if (in_array($type, ['integer', 'decimal', 'money', 'quantity'], true)
            && is_numeric((string) $value)) {
            return $value;
        }

        $text = (string) $value;

        if (preg_match('/^[\\x00-\\x20]*[=+\\-@]/u', $text) === 1) {
            return "'".$text;
        }

        return $text;
    }
}
