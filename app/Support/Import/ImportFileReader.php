<?php

namespace App\Support\Import;

use Generator;
use Illuminate\Support\Facades\Storage;
use JsonException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use RuntimeException;

final class ImportFileReader
{
    /**
     * Backward-compatible full materialization for callers that explicitly need an array.
     *
     * @return list<array<string, mixed>>
     */
    public function rows(string $disk, string $path, string $originalName): array
    {
        return iterator_to_array(
            $this->iterate($disk, $path, $originalName),
            false,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function previewRows(
        string $disk,
        string $path,
        string $originalName,
        int $limit = 20,
    ): array {
        $limit = max(1, $limit);
        $rows = [];

        foreach ($this->iterate($disk, $path, $originalName) as $row) {
            $rows[] = $row;

            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function iterate(string $disk, string $path, string $originalName): Generator
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $absolute = Storage::disk($disk)->path($path);

        yield from match ($extension) {
            'csv' => $this->csv($absolute),
            'json' => $this->json($absolute),
            'xlsx' => $this->xlsx($absolute),
            default => throw new RuntimeException('Desteklenmeyen içe aktarma dosya türü.'),
        };
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    private function csv(string $path): Generator
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('CSV dosyası açılamadı.');
        }

        try {
            $header = null;

            while (($data = fgetcsv($handle, separator: ';')) !== false) {
                if ($header === null) {
                    $header = array_map(
                        fn ($value): string => trim((string) $value),
                        $data,
                    );

                    continue;
                }

                if (count($data) === 1 && trim((string) $data[0]) === '') {
                    continue;
                }

                yield $this->combine($header, $data);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Streams a top-level JSON array one element at a time.
     *
     * @return Generator<int, array<string, mixed>>
     */
    private function json(string $path): Generator
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('JSON dosyası açılamadı.');
        }

        $started = false;
        $ended = false;
        $buffer = '';
        $depth = 0;
        $inString = false;
        $escaped = false;
        $firstChunk = true;

        try {
            while (! feof($handle)) {
                $chunk = fread($handle, 8192);

                if ($chunk === false) {
                    throw new RuntimeException('JSON dosyası okunamadı.');
                }

                if ($firstChunk) {
                    $firstChunk = false;

                    if (str_starts_with($chunk, "\xEF\xBB\xBF")) {
                        $chunk = substr($chunk, 3);
                    }
                }

                $length = strlen($chunk);

                for ($index = 0; $index < $length; $index++) {
                    $char = $chunk[$index];

                    if (! $started) {
                        if ($this->isJsonWhitespace($char)) {
                            continue;
                        }

                        if ($char !== '[') {
                            throw new RuntimeException('JSON kökü dizi olmalıdır.');
                        }

                        $started = true;

                        continue;
                    }

                    if ($ended) {
                        if (! $this->isJsonWhitespace($char)) {
                            throw new RuntimeException('JSON kök dizisinden sonra beklenmeyen veri var.');
                        }

                        continue;
                    }

                    if ($inString) {
                        $buffer .= $char;

                        if ($escaped) {
                            $escaped = false;
                        } elseif ($char === '\\') {
                            $escaped = true;
                        } elseif ($char === '"') {
                            $inString = false;
                        }

                        continue;
                    }

                    if ($char === '"') {
                        $inString = true;
                        $buffer .= $char;

                        continue;
                    }

                    if ($char === '{' || $char === '[') {
                        $depth++;
                        $buffer .= $char;

                        continue;
                    }

                    if ($char === '}') {
                        if ($depth < 1) {
                            throw new RuntimeException('JSON nesne kapanışı geçersiz.');
                        }

                        $depth--;
                        $buffer .= $char;

                        continue;
                    }

                    if ($char === ']') {
                        if ($depth > 0) {
                            $depth--;
                            $buffer .= $char;

                            continue;
                        }

                        $row = $this->decodeJsonElement($buffer);

                        if ($row !== null) {
                            yield $row;
                        }

                        $buffer = '';
                        $ended = true;

                        continue;
                    }

                    if ($char === ',' && $depth === 0) {
                        $row = $this->decodeJsonElement($buffer);

                        if ($row !== null) {
                            yield $row;
                        }

                        $buffer = '';

                        continue;
                    }

                    $buffer .= $char;
                }
            }

            if (! $started || ! $ended || $inString || $depth !== 0) {
                throw new RuntimeException('JSON dosyası tamamlanmamış veya geçersiz.');
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    private function xlsx(string $path): Generator
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $worksheets = $reader->listWorksheetInfo($path);
        $first = $worksheets[0] ?? null;

        if (! is_array($first)) {
            return;
        }

        $sheetName = (string) $first['worksheetName'];
        $lastColumn = (string) $first['lastColumnLetter'];
        $totalRows = (int) $first['totalRows'];

        if ($sheetName === '' || $totalRows < 1) {
            return;
        }

        $reader->setLoadSheetsOnly($sheetName);

        $filter = new class implements IReadFilter
        {
            private int $startRow = 2;

            private int $endRow = 1;

            public function setRows(int $startRow, int $endRow): void
            {
                $this->startRow = $startRow;
                $this->endRow = $endRow;
            }

            public function readCell(
                string $columnAddress,
                int $row,
                string $worksheetName = '',
            ): bool {
                return $row === 1
                    || ($row >= $this->startRow && $row <= $this->endRow);
            }
        };

        $reader->setReadFilter($filter);
        $filter->setRows(2, 1);
        $spreadsheet = $reader->load($path);

        try {
            $sheet = $spreadsheet->getSheetByName($sheetName)
                ?? $spreadsheet->getActiveSheet();
            $headerRows = $sheet->rangeToArray(
                "A1:{$lastColumn}1",
                null,
                true,
                true,
                false,
            );
            $header = array_map(
                fn ($value): string => trim((string) $value),
                $headerRows[0] ?? [],
            );
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }

        if ($header === []) {
            return;
        }

        $chunkSize = 1000;

        for ($startRow = 2; $startRow <= $totalRows; $startRow += $chunkSize) {
            $endRow = min($totalRows, $startRow + $chunkSize - 1);
            $filter->setRows($startRow, $endRow);
            $spreadsheet = $reader->load($path);

            try {
                $sheet = $spreadsheet->getSheetByName($sheetName)
                    ?? $spreadsheet->getActiveSheet();
                $rows = $sheet->rangeToArray(
                    "A{$startRow}:{$lastColumn}{$endRow}",
                    null,
                    true,
                    true,
                    false,
                );

                foreach ($rows as $row) {
                    yield $this->combine($header, $row);
                }
            } finally {
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
            }
        }
    }

    /**
     * @param  list<string>  $header
     * @param  array<int, mixed>  $data
     * @return array<string, mixed>
     */
    private function combine(array $header, array $data): array
    {
        $values = array_slice(
            array_pad($data, count($header), null),
            0,
            count($header),
        );
        return array_combine($header, $values);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonElement(string $buffer): ?array
    {
        $buffer = trim($buffer);

        if ($buffer === '') {
            return null;
        }

        try {
            $decoded = json_decode(
                $buffer,
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new RuntimeException('JSON dosyası okunamadı.', previous: $exception);
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function isJsonWhitespace(string $char): bool
    {
        return $char === ' '
            || $char === "\t"
            || $char === "\r"
            || $char === "\n";
    }
}
