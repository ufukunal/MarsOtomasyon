<?php

namespace App\Support\Import;

use Illuminate\Support\Facades\Storage;
use JsonException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

final class ImportFileReader
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function rows(string $disk, string $path, string $originalName): array
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $absolute = Storage::disk($disk)->path($path);

        return match ($extension) {
            'csv' => $this->csv($absolute),
            'json' => $this->json($absolute),
            'xlsx' => $this->xlsx($absolute),
            default => throw new RuntimeException('Desteklenmeyen içe aktarma dosya türü.'),
        };
    }

    private function csv(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('CSV dosyası açılamadı.');
        }

        try {
            $header = null;
            $rows = [];

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

                $rows[] = array_combine($header, array_pad($data, count($header), null));
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function json(string $path): array
    {
        try {
            $decoded = json_decode(
                file_get_contents($path) ?: '[]',
                true,
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new RuntimeException('JSON dosyası okunamadı.', previous: $exception);
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('JSON kökü dizi olmalıdır.');
        }

        return array_values(array_filter($decoded, 'is_array'));
    }

    private function xlsx(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        if ($rows === []) {
            return [];
        }

        $header = array_map(
            fn ($value): string => trim((string) $value),
            array_shift($rows),
        );

        return array_values(array_map(
            fn (array $row): array => array_combine($header, array_pad($row, count($header), null)),
            $rows,
        ));
    }
}
