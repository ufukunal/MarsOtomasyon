# G-006 — Belge numaralandırma

**Veritabanı: DÖNEM.** Her şirket+dönem kendi sayacını tutar; `company_id`
kolonu yoktur, veritabanı zaten o şirkete aittir.

## Amaç
Şirket + belge türü + yıl bazında, **kilitli**, boşluksuz numara üretimi.
Prototipteki çift numara hatasının çözümü.

## Önkoşul
G-003

## Şema

```php
Schema::create('number_series', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained();
    $table->string('document_type', 40);
    $table->string('prefix', 10);
    $table->unsignedSmallInteger('year');
    $table->unsignedBigInteger('last_number')->default(0);
    $table->unsignedTinyInteger('padding')->default(5);
    $table->timestamps();
    $table->unique(['company_id','document_type','year'], 'number_series_unique');
});
```

## Action

`app/Actions/Numbering/GenerateDocumentNumber.php`

```php
final class GenerateDocumentNumber
{
    public function handle(string $documentType, ?int $year = null): string
    {
        $year ??= now()->year;
        return DB::transaction(function () use ($documentType, $year) {
            $series = NumberSeries::query()
                ->where('document_type', $documentType)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (! $series) {
                $series = NumberSeries::create([
                    'document_type' => $documentType,
                    'prefix'        => config("numbering.prefixes.{$documentType}", 'DOC'),
                    'year'          => $year,
                    'last_number'   => 0,
                    'padding'       => 5,
                ]);
            }

            $series->increment('last_number');

            return sprintf('%s-%d-%s',
                $series->prefix, $series->year,
                str_pad((string) $series->last_number, $series->padding, '0', STR_PAD_LEFT));
        });
    }
}
```

## config/numbering.php

```php
return ['prefixes' => [
    'quote' => 'TKL', 'sales_order' => 'SIP', 'dispatch' => 'IRS',
    'sales_invoice' => 'SF', 'proforma' => 'PRF', 'purchase_order' => 'PO',
    'goods_receipt' => 'MK', 'supplier_invoice' => 'AF', 'sales_return' => 'SI',
    'purchase_return' => 'AI', 'transfer' => 'TRF', 'stock_count' => 'CNT',
    'warehouse_slip' => 'AMB', 'production_order' => 'UE',
    'collection' => 'TH', 'payment' => 'OD',
]];
```

## Kritik notlar
- `lockForUpdate()` **atlanmamalı** — çift numaranın tek çaresi budur
- Numara, belgeyi kesinleştiren transaction'ın **içinde** üretilir
- Taslak belgede numara **yoktur**

## Kabul ölçütü
- Peş peşe 3 çağrı: `SF-2026-00001`, `00002`, `00003`
- Farklı şirkette sayaç ayrı başlar
- Transaction geri alınırsa numara artmaz

## İstem
> number_series tablosu için migration, NumberSeries modeli,
> GenerateDocumentNumber action'ı ve config/numbering.php dosyasını
> yukarıdaki kodla birebir yaz. lockForUpdate satırını kesinlikle atlama.
