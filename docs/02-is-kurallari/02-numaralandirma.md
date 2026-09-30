# Belge numaralandırma

## Kural

- Numara **yalnızca kesinleştirme anında** verilir. Taslakta numara yoktur.
- Şirket + belge türü + yıl bazında ayrı sayaç.
- Boşluk olmaz, iki kullanıcı aynı numarayı alamaz.

## Uygulama

```php
final class GenerateDocumentNumber
{
    public function handle(string $documentType, int $year = null): string
    {
        $year ??= now()->year;
        $companyId = CompanyContext::id();

        return DB::transaction(function () use ($documentType, $year, $companyId) {
            $series = NumberSeries::query()
                ->where('company_id', $companyId)
                ->where('document_type', $documentType)
                ->where('year', $year)
                ->lockForUpdate()               // <-- kritik
                ->first();

            if (! $series) {
                $series = NumberSeries::create([
                    'company_id'    => $companyId,
                    'document_type' => $documentType,
                    'prefix'        => config("numbering.prefixes.$documentType", 'DOC'),
                    'year'          => $year,
                    'last_number'   => 0,
                    'padding'       => 5,
                ]);
            }

            $series->increment('last_number');

            return sprintf('%s-%d-%s',
                $series->prefix,
                $series->year,
                str_pad((string) $series->last_number, $series->padding, '0', STR_PAD_LEFT)
            );
        });
    }
}
```

## Neden `lockForUpdate`

İki kullanıcı aynı anda fatura kesinleştirirse ikisi de aynı `last_number`
değerini okur ve aynı numarayı alır. Satır kilidi bunu engeller.
**Prototipteki hata buydu.**

## Çağıran işlemin içinde olmalı

Numara üretimi, belgeyi kesinleştiren transaction'ın **içinde** çağrılır.
İşlem geri alınırsa numara da geri alınır, boşluk oluşmaz.
