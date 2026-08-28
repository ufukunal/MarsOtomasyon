<?php

namespace App\Modules\Treasury\Import;

use DateTimeImmutable;
use DomainException;
use SimpleXMLElement;
use ZipArchive;

final class BankStatementParser
{
    /**
     * @return list<array{
     *     booking_date:string,
     *     value_date:?string,
     *     currency_code:string,
     *     signed_amount:string,
     *     reference:?string,
     *     description:?string,
     *     external_key:string
     * }>
     */
    public function parse(string $format, string $path, string $defaultCurrency): array
    {
        return match ($format) {
            'csv' => $this->csv($path, $defaultCurrency),
            'xlsx' => $this->xlsx($path, $defaultCurrency),
            'mt940' => $this->mt940($path, $defaultCurrency),
            default => throw new DomainException('Desteklenmeyen banka ekstresi formatı.'),
        };
    }

    /**
     * @return list<array{
     *     booking_date:string,
     *     value_date:?string,
     *     currency_code:string,
     *     signed_amount:string,
     *     reference:?string,
     *     description:?string,
     *     external_key:string
     * }>
     */
    private function csv(string $path, string $defaultCurrency): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new DomainException('CSV dosyası açılamadı.');
        }

        try {
            $header = fgetcsv($handle, separator: ',');
            if ($header === false) {
                throw new DomainException('CSV başlık satırı bulunamadı.');
            }

            $separator = ',';
            if (count($header) === 1 && str_contains((string) $header[0], ';')) {
                rewind($handle);
                $header = fgetcsv($handle, separator: ';');
                $separator = ';';
            }
            if ($header === false) {
                throw new DomainException('CSV başlık satırı okunamadı.');
            }

            $keys = array_map(fn (mixed $value): string => $this->header((string) $value), $header);
            $rows = [];
            $lineNumber = 1;

            while (($values = fgetcsv($handle, separator: $separator)) !== false) {
                $lineNumber++;
                if ($values === [null] || $values === []) {
                    continue;
                }

                $record = [];
                foreach ($keys as $index => $key) {
                    $record[$key] = isset($values[$index]) ? trim((string) $values[$index]) : '';
                }
                if (implode('', $record) === '') {
                    continue;
                }

                $rows[] = $this->row($record, $defaultCurrency, 'csv:'.$lineNumber);
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return list<array{
     *     booking_date:string,
     *     value_date:?string,
     *     currency_code:string,
     *     signed_amount:string,
     *     reference:?string,
     *     description:?string,
     *     external_key:string
     * }>
     */
    private function xlsx(string $path, string $defaultCurrency): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new DomainException('XLSX içe aktarma için PHP zip extension gereklidir.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new DomainException('XLSX dosyası açılamadı.');
        }

        try {
            $shared = $this->xlsxSharedStrings($zip);
            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
            if (! is_string($sheetXml)) {
                throw new DomainException('XLSX ilk çalışma sayfası bulunamadı.');
            }

            $sheet = new SimpleXMLElement($sheetXml);
            $namespaces = $sheet->getNamespaces(true);
            $namespace = $namespaces[''] ?? null;
            $sheetNodes = $namespace === null ? $sheet : $sheet->children($namespace);
            $rawRows = [];

            foreach ($sheetNodes->sheetData->row as $row) {
                $rowNodes = $namespace === null ? $row : $row->children($namespace);
                $cells = [];
                foreach ($rowNodes->c as $cell) {
                    $reference = (string) $cell['r'];
                    preg_match('/^[A-Z]+/', $reference, $columnMatch);
                    $column = $columnMatch[0] ?? '';
                    if ($column === '') {
                        continue;
                    }

                    $cellNodes = $namespace === null ? $cell : $cell->children($namespace);
                    $type = (string) $cell['t'];
                    if ($type === 'inlineStr') {
                        $inline = $cellNodes->is;
                        $inlineNodes = $namespace === null ? $inline : $inline->children($namespace);
                        $value = (string) $inlineNodes->t;
                    } else {
                        $value = (string) $cellNodes->v;
                        if ($type === 's' && ctype_digit($value)) {
                            $value = $shared[(int) $value] ?? '';
                        }
                    }

                    $cells[$column] = trim($value);
                }
                $rawRows[] = $cells;
            }

            if ($rawRows === []) {
                throw new DomainException('XLSX veri satırı bulunamadı.');
            }

            $headerRow = array_shift($rawRows);
            if ($headerRow === null) {
                throw new DomainException('XLSX başlık satırı bulunamadı.');
            }

            $columnKeys = [];
            foreach ($headerRow as $column => $name) {
                $columnKeys[$column] = $this->header($name);
            }

            $rows = [];
            foreach ($rawRows as $index => $cells) {
                $record = [];
                foreach ($columnKeys as $column => $key) {
                    $record[$key] = $cells[$column] ?? '';
                }
                if (implode('', $record) === '') {
                    continue;
                }

                $rows[] = $this->row($record, $defaultCurrency, 'xlsx:'.($index + 2));
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    /** @return list<string> */
    private function xlsxSharedStrings(ZipArchive $zip): array
    {
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if (! is_string($sharedXml)) {
            return [];
        }

        $xml = new SimpleXMLElement($sharedXml);
        $namespaces = $xml->getNamespaces(true);
        $namespace = $namespaces[''] ?? null;
        $nodes = $namespace === null ? $xml : $xml->children($namespace);
        $shared = [];

        foreach ($nodes->si as $item) {
            $itemNodes = $namespace === null ? $item : $item->children($namespace);
            if (isset($itemNodes->t)) {
                $shared[] = (string) $itemNodes->t;
                continue;
            }

            $text = '';
            foreach ($itemNodes->r as $run) {
                $runNodes = $namespace === null ? $run : $run->children($namespace);
                $text .= (string) $runNodes->t;
            }
            $shared[] = $text;
        }

        return $shared;
    }

    /**
     * @return list<array{
     *     booking_date:string,
     *     value_date:?string,
     *     currency_code:string,
     *     signed_amount:string,
     *     reference:?string,
     *     description:?string,
     *     external_key:string
     * }>
     */
    private function mt940(string $path, string $defaultCurrency): array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new DomainException('MT940 dosyası okunamadı.');
        }

        $lines = preg_split('/\R/', $content) ?: [];
        $rows = [];

        foreach ($lines as $index => $line) {
            if (! str_starts_with($line, ':61:')) {
                continue;
            }

            $body = substr($line, 4);
            if (preg_match('/^(?<date>\d{6})(?<entry>\d{4})?(?<dc>[DC])(?<amount>\d+(?:,\d{1,6})?)(?<tail>.*)$/', $body, $match) !== 1) {
                throw new DomainException('MT940 :61: satırı çözümlenemedi: '.($index + 1));
            }

            $date = DateTimeImmutable::createFromFormat('!ymd', $match['date']);
            if (! $date instanceof DateTimeImmutable) {
                throw new DomainException('MT940 tarih hatası.');
            }

            $amount = str_replace(',', '.', $match['amount']);
            if ($match['dc'] === 'D') {
                $amount = '-'.$amount;
            }

            $description = null;
            if (isset($lines[$index + 1]) && str_starts_with($lines[$index + 1], ':86:')) {
                $description = trim(substr($lines[$index + 1], 4));
            }
            $reference = trim($match['tail']) !== '' ? trim($match['tail']) : null;

            $rows[] = $this->normalized(
                bookingDate: $date->format('Y-m-d'),
                valueDate: null,
                currency: $defaultCurrency,
                amount: $amount,
                reference: $reference,
                description: $description,
                externalKey: 'mt940:'.hash('sha256', $line.'|'.($description ?? '').'|'.$index),
            );
        }

        return $rows;
    }

    /**
     * @param  array<string, string>  $record
     * @return array{
     *     booking_date:string,
     *     value_date:?string,
     *     currency_code:string,
     *     signed_amount:string,
     *     reference:?string,
     *     description:?string,
     *     external_key:string
     * }
     */
    private function row(array $record, string $defaultCurrency, string $fallbackKey): array
    {
        $bookingDate = $this->date($record['booking_date'] ?? '');
        $valueDate = ($record['value_date'] ?? '') === ''
            ? null
            : $this->date($record['value_date']);
        $currency = strtoupper($record['currency_code'] ?? $defaultCurrency);
        $amount = str_replace(',', '.', $record['signed_amount'] ?? '');
        $reference = ($record['reference'] ?? '') === '' ? null : $record['reference'];
        $description = ($record['description'] ?? '') === '' ? null : $record['description'];
        $externalKey = ($record['external_key'] ?? '') !== ''
            ? $record['external_key']
            : hash('sha256', implode('|', [$fallbackKey, $bookingDate, $amount, $reference ?? '', $description ?? '']));

        return $this->normalized(
            $bookingDate,
            $valueDate,
            $currency,
            $amount,
            $reference,
            $description,
            $externalKey,
        );
    }

    /**
     * @return array{
     *     booking_date:string,
     *     value_date:?string,
     *     currency_code:string,
     *     signed_amount:string,
     *     reference:?string,
     *     description:?string,
     *     external_key:string
     * }
     */
    private function normalized(
        string $bookingDate,
        ?string $valueDate,
        string $currency,
        string $amount,
        ?string $reference,
        ?string $description,
        string $externalKey,
    ): array {
        $amount = $this->decimal($amount);
        if (preg_match('/^[A-Z]{3}$/D', $currency) !== 1) {
            throw new DomainException('Ekstre para birimi üç harfli ISO kodu olmalıdır.');
        }

        return [
            'booking_date' => $bookingDate,
            'value_date' => $valueDate,
            'currency_code' => $currency,
            'signed_amount' => $amount,
            'reference' => $reference === null ? null : mb_substr($reference, 0, 255),
            'description' => $description,
            'external_key' => mb_substr($externalKey, 0, 160),
        ];
    }

    private function decimal(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^-?\d+(?:\.\d{1,6})?$/D', $value) !== 1) {
            throw new DomainException('Ekstre tutarı sıfır olmayan en fazla 6 ondalıklı decimal olmalıdır.');
        }

        $negative = str_starts_with($value, '-');
        $unsigned = $negative ? substr($value, 1) : $value;
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        if (strlen($whole) > 14) {
            throw new DomainException('Ekstre tutarı numeric(20,6) sınırını aşıyor.');
        }
        $fraction = str_pad($fraction, 6, '0');
        if ($whole === '0' && $fraction === '000000') {
            throw new DomainException('Ekstre tutarı sıfır olamaz.');
        }

        return ($negative ? '-' : '').$whole.'.'.$fraction;
    }

    private function date(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^\d+(?:\.\d+)?$/D', $value) === 1) {
            [$wholeDays] = explode('.', $value, 2);
            $days = (int) $wholeDays;

            return (new DateTimeImmutable('1899-12-30'))
                ->modify('+'.$days.' days')
                ->format('Y-m-d');
        }

        foreach (['Y-m-d', 'd.m.Y', 'd/m/Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);
            if ($date instanceof DateTimeImmutable && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        throw new DomainException('Ekstre tarihi geçersiz: '.$value);
    }

    private function header(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, [
            'ı' => 'i',
            'ş' => 's',
            'ğ' => 'g',
            'ü' => 'u',
            'ö' => 'o',
            'ç' => 'c',
            ' ' => '_',
            '-' => '_',
        ]);

        return match ($value) {
            'date', 'tarih', 'booking_date', 'islem_tarihi' => 'booking_date',
            'value_date', 'valör', 'valor', 'valor_tarihi' => 'value_date',
            'amount', 'tutar', 'signed_amount' => 'signed_amount',
            'currency', 'currency_code', 'doviz', 'para_birimi' => 'currency_code',
            'reference', 'referans', 'ref' => 'reference',
            'description', 'aciklama', 'açıklama' => 'description',
            'external_key', 'external_id', 'harici_id' => 'external_key',
            default => $value,
        };
    }
}
