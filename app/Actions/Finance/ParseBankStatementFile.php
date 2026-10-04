<?php

namespace App\Actions\Finance;

use Carbon\CarbonImmutable;
use DomainException;
use SimpleXMLElement;
use SplFileObject;
use ZipArchive;

final class ParseBankStatementFile
{
    /** @return list<array{date:string,value_date:?string,reference:?string,description:string,direction:string,amount:string,balance:?string}> */
    public function handle(string $path, string $format = 'auto'): array
    {
        if (! is_file($path)) {
            throw new DomainException('Banka ekstre dosyası bulunamadı.');
        }

        $format = strtolower(trim($format));

        if ($format === 'auto') {
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $format = match ($extension) {
                'csv', 'txt' => 'csv',
                'xlsx' => 'xlsx',
                'mt940', 'sta' => 'mt940',
                default => throw new DomainException('Ekstre dosya formatı otomatik algılanamadı.'),
            };
        }

        return match ($format) {
            'csv' => $this->parseCsv($path),
            'xlsx' => $this->parseXlsx($path),
            'mt940' => $this->parseMt940($path),
            default => throw new DomainException('Desteklenmeyen ekstre formatı.'),
        };
    }

    /** @return list<array{date:string,value_date:?string,reference:?string,description:string,direction:string,amount:string,balance:?string}> */
    private function parseCsv(string $path): array
    {
        $sample = (string) file_get_contents($path, false, null, 0, 4096);
        $delimiter = $this->detectDelimiter($sample);
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
        $file->setCsvControl($delimiter);

        $headers = null;
        $rows = [];

        foreach ($file as $raw) {
            if (! is_array($raw) || $raw === [null]) {
                continue;
            }

            $values = array_map(fn ($value): string => trim((string) $value), $raw);

            if ($headers === null) {
                $headers = array_map([$this, 'normalizeHeader'], $values);

                continue;
            }

            $row = [];

            foreach ($headers as $index => $header) {
                if ($header !== '') {
                    $row[$header] = $values[$index] ?? '';
                }
            }

            $normalized = $this->normalizeRow($row);

            if ($normalized !== null) {
                $rows[] = $normalized;
            }
        }

        if ($rows === []) {
            throw new DomainException('CSV ekstre dosyasında aktarılabilir hareket bulunamadı.');
        }

        return $rows;
    }

    /** @return list<array{date:string,value_date:?string,reference:?string,description:string,direction:string,amount:string,balance:?string}> */
    private function parseXlsx(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new DomainException('XLSX içe aktarma için PHP zip uzantısı gereklidir.');
        }

        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw new DomainException('XLSX dosyası açılamadı.');
        }

        try {
            $sharedStrings = [];
            $sharedXml = $zip->getFromName('xl/sharedStrings.xml');

            if ($sharedXml !== false) {
                $xml = new SimpleXMLElement($sharedXml);

                foreach ($xml->si as $item) {
                    if (isset($item->t)) {
                        $sharedStrings[] = (string) $item->t;
                    } else {
                        $text = '';

                        foreach ($item->r as $run) {
                            $text .= (string) $run->t;
                        }

                        $sharedStrings[] = $text;
                    }
                }
            }

            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');

            if ($sheetXml === false) {
                throw new DomainException('XLSX ilk çalışma sayfası bulunamadı.');
            }

            $sheet = new SimpleXMLElement($sheetXml);
            $table = [];

            foreach ($sheet->sheetData->row as $row) {
                $values = [];

                foreach ($row->c as $cell) {
                    $reference = (string) $cell['r'];
                    preg_match('/^[A-Z]+/', $reference, $match);
                    $column = $this->columnIndex($match[0] ?? 'A');
                    $type = (string) $cell['t'];
                    $value = (string) $cell->v;

                    if ($type === 's') {
                        $value = $sharedStrings[(int) $value] ?? '';
                    } elseif ($type === 'inlineStr') {
                        $value = (string) $cell->is->t;
                    }

                    $values[$column] = trim($value);
                }

                if ($values !== []) {
                    ksort($values);
                    $table[] = $values;
                }
            }

            if ($table === []) {
                throw new DomainException('XLSX ekstre dosyası boş.');
            }

            $headerRow = array_shift($table);
            $maxColumn = max(array_keys($headerRow));
            $headers = [];

            for ($i = 0; $i <= $maxColumn; $i++) {
                $headers[$i] = $this->normalizeHeader($headerRow[$i] ?? '');
            }

            $rows = [];

            foreach ($table as $values) {
                $row = [];

                foreach ($headers as $index => $header) {
                    if ($header !== '') {
                        $row[$header] = $values[$index] ?? '';
                    }
                }

                $normalized = $this->normalizeRow($row);

                if ($normalized !== null) {
                    $rows[] = $normalized;
                }
            }

            if ($rows === []) {
                throw new DomainException('XLSX ekstre dosyasında aktarılabilir hareket bulunamadı.');
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    /** @return list<array{date:string,value_date:?string,reference:?string,description:string,direction:string,amount:string,balance:?string}> */
    private function parseMt940(string $path): array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            throw new DomainException('MT940 dosyası okunamadı.');
        }

        $rows = [];
        $pending = null;

        foreach ($lines as $line) {
            if (str_starts_with($line, ':61:')) {
                if ($pending !== null) {
                    $rows[] = $pending;
                }

                $value = substr($line, 4);

                if (! preg_match('/^(\d{6})(\d{4})?([CD])([0-9,\.]+)(.*)$/', $value, $match)) {
                    continue;
                }

                $dateObject = CarbonImmutable::createFromFormat('ymd', $match[1])->startOfDay();
                $date = $dateObject->toDateString();
                $valueDate = null;

                if ($match[2] !== '') {
                    $month = (int) substr($match[2], 0, 2);
                    $day = (int) substr($match[2], 2, 2);
                    $candidate = CarbonImmutable::create($dateObject->year, $month, $day)->startOfDay();

                    if ($candidate->diffInDays($dateObject, false) > 180) {
                        $candidate = $candidate->subYear();
                    } elseif ($candidate->diffInDays($dateObject, false) < -180) {
                        $candidate = $candidate->addYear();
                    }

                    $valueDate = $candidate->toDateString();
                }
                $tail = trim($match[5]);
                $reference = null;

                if (preg_match('/N[A-Z0-9]{3}([^\/]+)(?:\/\/([^\s]+))?/', $tail, $refMatch)) {
                    $reference = trim((string) ($refMatch[2] ?? $refMatch[1]));
                }

                $pending = [
                    'date' => $date,
                    'value_date' => $valueDate,
                    'reference' => $reference ?: null,
                    'description' => $tail,
                    'direction' => $match[3] === 'C' ? 'in' : 'out',
                    'amount' => $this->decimal($match[4]),
                    'balance' => null,
                ];

                continue;
            }

            if ($pending !== null && str_starts_with($line, ':86:')) {
                $pending['description'] = trim(substr($line, 4));
            }
        }

        if ($pending !== null) {
            $rows[] = $pending;
        }

        if ($rows === []) {
            throw new DomainException('MT940 ekstre dosyasında aktarılabilir hareket bulunamadı.');
        }

        return $rows;
    }

    /**
     * @param array<string,string> $row
     * @return array{date:string,value_date:?string,reference:?string,description:string,direction:string,amount:string,balance:?string}|null
     */
    private function normalizeRow(array $row): ?array
    {
        $dateValue = $row['date'] ?? '';

        if ($dateValue === '') {
            return null;
        }

        $credit = $this->decimal($row['credit'] ?? '');
        $debit = $this->decimal($row['debit'] ?? '');
        $signedAmount = $this->decimal($row['amount'] ?? '');
        $direction = strtolower($row['direction'] ?? '');

        if (bccomp($credit, '0', 4) > 0) {
            $direction = 'in';
            $amount = $credit;
        } elseif (bccomp($debit, '0', 4) > 0) {
            $direction = 'out';
            $amount = $debit;
        } elseif ($signedAmount !== '0.0000') {
            $direction = str_starts_with($signedAmount, '-') ? 'out' : ($direction ?: 'in');
            $amount = str_starts_with($signedAmount, '-') ? substr($signedAmount, 1) : $signedAmount;
        } else {
            return null;
        }

        if (in_array($direction, ['giris', 'alacak', 'credit', 'c'], true)) {
            $direction = 'in';
        }

        if (in_array($direction, ['cikis', 'borc', 'debit', 'd'], true)) {
            $direction = 'out';
        }

        if (! in_array($direction, ['in', 'out'], true)) {
            throw new DomainException('Ekstre satırı yönü belirlenemedi.');
        }

        return [
            'date' => $this->date($dateValue),
            'value_date' => ($row['value_date'] ?? '') !== ''
                ? $this->date($row['value_date'])
                : null,
            'reference' => trim($row['reference'] ?? '') ?: null,
            'description' => trim($row['description'] ?? ''),
            'direction' => $direction,
            'amount' => bcadd($amount, '0', 4),
            'balance' => ($row['balance'] ?? '') !== ''
                ? $this->decimal($row['balance'])
                : null,
        ];
    }

    private function date(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            throw new DomainException('Ekstre satırı tarihi boş olamaz.');
        }

        if (is_numeric($value)) {
            $serial = (float) $value;

            if ($serial >= 1 && $serial <= 100000) {
                return CarbonImmutable::create(1899, 12, 30)
                    ->addDays((int) floor($serial))
                    ->toDateString();
            }
        }

        return CarbonImmutable::parse($value)->toDateString();
    }

    private function normalizeHeader(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = strtr($value, [
            'ı' => 'i', 'ş' => 's', 'ğ' => 'g', 'ü' => 'u', 'ö' => 'o', 'ç' => 'c',
        ]);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';

        return match (trim($value, '_')) {
            'tarih', 'islem_tarihi', 'transaction_date', 'date' => 'date',
            'valor', 'valor_tarihi', 'value_date' => 'value_date',
            'referans', 'ref', 'reference', 'transaction_reference' => 'reference',
            'aciklama', 'description', 'details' => 'description',
            'borc', 'debit', 'cikis' => 'debit',
            'alacak', 'credit', 'giris' => 'credit',
            'tutar', 'amount' => 'amount',
            'yon', 'direction' => 'direction',
            'bakiye', 'balance' => 'balance',
            default => '',
        };
    }

    private function decimal(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '0.0000';
        }

        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        $value = preg_replace('/[^0-9,\.]/', '', $value) ?? '';

        if (str_contains($value, ',') && str_contains($value, '.')) {
            if (strrpos($value, ',') > strrpos($value, '.')) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        }

        $value = $value === '' ? '0' : $value;
        $normalized = bcadd($value, '0', 4);

        return $negative ? bcmul($normalized, '-1', 4) : $normalized;
    }

    private function detectDelimiter(string $sample): string
    {
        $counts = [
            ';' => substr_count($sample, ';'),
            ',' => substr_count($sample, ','),
            "\t" => substr_count($sample, "\t"),
        ];

        arsort($counts);

        return (string) array_key_first($counts);
    }

    private function columnIndex(string $letters): int
    {
        $result = 0;

        foreach (str_split($letters) as $letter) {
            $result = ($result * 26) + (ord($letter) - 64);
        }

        return $result - 1;
    }
}
