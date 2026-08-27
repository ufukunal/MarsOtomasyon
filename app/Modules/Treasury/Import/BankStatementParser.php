<?php

namespace App\Modules\Treasury\Import;

use DateTimeImmutable;
use DomainException;
use SimpleXMLElement;
use ZipArchive;

final class BankStatementParser
{
    /** @return list<array{booking_date:string,value_date:?string,currency_code:string,signed_amount:string,reference:?string,description:?string,external_key:string}> */
    public function parse(string $format, string $path, string $defaultCurrency): array
    {
        return match ($format) {
            'csv' => $this->csv($path, $defaultCurrency),
            'xlsx' => $this->xlsx($path, $defaultCurrency),
            'mt940' => $this->mt940($path, $defaultCurrency),
            default => throw new DomainException('Desteklenmeyen banka ekstresi formatı.'),
        };
    }

    /** @return list<array{booking_date:string,value_date:?string,currency_code:string,signed_amount:string,reference:?string,description:?string,external_key:string}> */
    private function csv(string $path, string $defaultCurrency): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) throw new DomainException('CSV dosyası açılamadı.');
        try {
            $header = fgetcsv($handle, separator: ',');
            if ($header === false) throw new DomainException('CSV başlık satırı bulunamadı.');
            if (count($header) === 1 && str_contains((string) $header[0], ';')) {
                rewind($handle); $header = fgetcsv($handle, separator: ';'); $separator = ';';
            } else $separator = ',';
            if ($header === false) throw new DomainException('CSV başlık satırı okunamadı.');
            $keys = array_map(fn ($v): string => $this->header((string) $v), $header);
            $rows = [];
            $line = 1;
            while (($values = fgetcsv($handle, separator: $separator)) !== false) {
                $line++;
                if ($values === [null] || $values === []) continue;
                $record = [];
                foreach ($keys as $i => $key) $record[$key] = isset($values[$i]) ? trim((string) $values[$i]) : '';
                $rows[] = $this->row($record, $defaultCurrency, 'csv:'.$line);
            }
            return $rows;
        } finally { fclose($handle); }
    }

    /** @return list<array{booking_date:string,value_date:?string,currency_code:string,signed_amount:string,reference:?string,description:?string,external_key:string}> */
    private function xlsx(string $path, string $defaultCurrency): array
    {
        if (! class_exists(ZipArchive::class)) throw new DomainException('XLSX içe aktarma için PHP zip extension gereklidir.');
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw new DomainException('XLSX dosyası açılamadı.');
        try {
            $shared = [];
            $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
            if (is_string($sharedXml)) {
                $xml = new SimpleXMLElement($sharedXml);
                foreach ($xml->si as $item) {
                    $text = '';
                    if (isset($item->t)) $text = (string) $item->t;
                    else foreach ($item->r as $run) $text .= (string) $run->t;
                    $shared[] = $text;
                }
            }
            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
            if (! is_string($sheetXml)) throw new DomainException('XLSX ilk çalışma sayfası bulunamadı.');
            $sheet = new SimpleXMLElement($sheetXml);
            $rawRows = [];
            foreach ($sheet->sheetData->row as $row) {
                $cells = [];
                foreach ($row->c as $cell) {
                    $ref = (string) $cell['r'];
                    preg_match('/^[A-Z]+/', $ref, $match);
                    $column = $match[0] ?? '';
                    $type = (string) $cell['t'];
                    if ($type === 'inlineStr') $value = (string) $cell->is->t;
                    else {
                        $value = (string) $cell->v;
                        if ($type === 's' && ctype_digit($value)) $value = $shared[(int) $value] ?? '';
                    }
                    $cells[$column] = trim($value);
                }
                $rawRows[] = $cells;
            }
            if ($rawRows === []) throw new DomainException('XLSX veri satırı bulunamadı.');
            $headerRow = array_shift($rawRows);
            $columnKeys = [];
            foreach ($headerRow as $column => $name) $columnKeys[$column] = $this->header($name);
            $rows = [];
            foreach ($rawRows as $index => $cells) {
                $record = [];
                foreach ($columnKeys as $column => $key) $record[$key] = $cells[$column] ?? '';
                if (implode('', $record) === '') continue;
                $rows[] = $this->row($record, $defaultCurrency, 'xlsx:'.($index + 2));
            }
            return $rows;
        } finally { $zip->close(); }
    }

    /** @return list<array{booking_date:string,value_date:?string,currency_code:string,signed_amount:string,reference:?string,description:?string,external_key:string}> */
    private function mt940(string $path, string $defaultCurrency): array
    {
        $content = file_get_contents($path);
        if ($content === false) throw new DomainException('MT940 dosyası okunamadı.');
        $lines = preg_split('/\R/', $content) ?: [];
        $rows = [];
        foreach ($lines as $i => $line) {
            if (! str_starts_with($line, ':61:')) continue;
            $body = substr($line, 4);
            if (preg_match('/^(?<date>\d{6})(?<entry>\d{4})?(?<dc>[DC])(?<amount>\d+(?:,\d{1,6})?)(?<tail>.*)$/', $body, $m) !== 1) {
                throw new DomainException('MT940 :61: satırı çözümlenemedi: '.($i + 1));
            }
            $date = DateTimeImmutable::createFromFormat('!ymd', $m['date']);
            if (! $date instanceof DateTimeImmutable) throw new DomainException('MT940 tarih hatası.');
            $amount = str_replace(',', '.', $m['amount']);
            if ($m['dc'] === 'D') $amount = '-'.$amount;
            $description = null;
            if (isset($lines[$i + 1]) && str_starts_with($lines[$i + 1], ':86:')) $description = trim(substr($lines[$i + 1], 4));
            $reference = trim($m['tail']) !== '' ? trim($m['tail']) : null;
            $rows[] = $this->normalized(
                $date->format('Y-m-d'), null, $defaultCurrency, $amount, $reference, $description,
                'mt940:'.hash('sha256', $line.'|'.($description ?? '').'|'.$i),
            );
        }
        return $rows;
    }

    /** @param array<string,string> $record
     * @return array{booking_date:string,value_date:?string,currency_code:string,signed_amount:string,reference:?string,description:?string,external_key:string}
     */
    private function row(array $record, string $defaultCurrency, string $fallbackKey): array
    {
        $booking = $this->date($record['booking_date'] ?? '');
        $valueDate = ($record['value_date'] ?? '') === '' ? null : $this->date($record['value_date']);
        $currency = strtoupper($record['currency_code'] ?? $defaultCurrency);
        $amount = str_replace(',', '.', $record['signed_amount'] ?? '');
        $reference = ($record['reference'] ?? '') === '' ? null : $record['reference'];
        $description = ($record['description'] ?? '') === '' ? null : $record['description'];
        $external = ($record['external_key'] ?? '') !== '' ? $record['external_key'] : hash('sha256', implode('|', [$fallbackKey,$booking,$amount,$reference ?? '',$description ?? '']));
        return $this->normalized($booking, $valueDate, $currency, $amount, $reference, $description, $external);
    }

    /** @return array{booking_date:string,value_date:?string,currency_code:string,signed_amount:string,reference:?string,description:?string,external_key:string} */
    private function normalized(string $booking, ?string $valueDate, string $currency, string $amount, ?string $reference, ?string $description, string $external): array
    {
        if (preg_match('/^-?\d+(?:\.\d{1,6})?$/D', $amount) !== 1 || preg_match('/^-?0+(?:\.0+)?$/D', $amount) === 1) throw new DomainException('Ekstre tutarı sıfır olmayan en fazla 6 ondalıklı decimal olmalıdır.');
        if (preg_match('/^[A-Z]{3}$/D', $currency) !== 1) throw new DomainException('Ekstre para birimi üç harfli ISO kodu olmalıdır.');
        $amount = number_format((float) $amount, 6, '.', '');
        return ['booking_date'=>$booking,'value_date'=>$valueDate,'currency_code'=>$currency,'signed_amount'=>$amount,'reference'=>$reference,'description'=>$description,'external_key'=>mb_substr($external,0,160)];
    }

    private function date(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^\d+(?:\.\d+)?$/D', $value) === 1) {
            $days = (int) floor((float) $value);
            return (new DateTimeImmutable('1899-12-30'))->modify('+'.$days.' days')->format('Y-m-d');
        }
        foreach (['Y-m-d','d.m.Y','d/m/Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);
            if ($date instanceof DateTimeImmutable && $date->format($format) === $value) return $date->format('Y-m-d');
        }
        throw new DomainException('Ekstre tarihi geçersiz: '.$value);
    }

    private function header(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, ['ı'=>'i','ş'=>'s','ğ'=>'g','ü'=>'u','ö'=>'o','ç'=>'c',' '=>'_','-'=>'_']);
        return match ($value) {
            'date','tarih','booking_date','islem_tarihi' => 'booking_date',
            'value_date','valör','valor','valor_tarihi' => 'value_date',
            'amount','tutar','signed_amount' => 'signed_amount',
            'currency','currency_code','doviz','para_birimi' => 'currency_code',
            'reference','referans','ref' => 'reference',
            'description','aciklama','açıklama' => 'description',
            'external_key','external_id','harici_id' => 'external_key',
            default => $value,
        };
    }
}
