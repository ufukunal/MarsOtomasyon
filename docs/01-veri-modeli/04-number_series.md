# number_series

**Veritabanı: DÖNEM**

`company_id` kolonu **yoktur** — veritabanı zaten o şirkete ve yıla aittir.



## Amaç

Belge numarası üretimi. **Şirket + belge türü + yıl** bazında ayrı sayaç.
Numara üretimi veritabanı kilidiyle yapılır; boşluk olmaz, iki kullanıcı
aynı numarayı alamaz.

Prototipte bu sorun vardı: numara üretimi işlemsel değildi.

## Şema

```php
Schema::connection('period')->create('number_series', function (Blueprint $table) {
    $table->id();
    $table->string('document_type', 40);        // sales_invoice, quote, ...
    $table->string('prefix', 10);               // SF, TKL, SIP
    $table->unsignedSmallInteger('year');
    $table->unsignedBigInteger('last_number')->default(0);
    $table->unsignedTinyInteger('padding')->default(5);   // 00001
    $table->timestamps();

    $table->unique(['document_type', 'year'], 'number_series_unique');
});
```

## Üretim kuralı

```
SF-2026-00001
^^ prefix
   ^^^^ yıl
        ^^^^^ last_number + 1, padding kadar sıfırla
```

- Yıl başında sayaç sıfırdan başlar (yeni satır açılır)
- **Numara yalnızca kesinleştirme anında verilir.** Taslak belgede numara yoktur.
- Üretim `SELECT ... FOR UPDATE` ile, çağıran işlemin (transaction) içinde yapılır
- İşlem geri alınırsa numara da geri alınır — boşluk oluşmaz

## Seed edilecek belge türleri

| document_type | prefix |
|---|---|
| quote | TKL |
| sales_order | SIP |
| dispatch | IRS |
| sales_invoice | SF |
| proforma | PRF |
| purchase_order | PO |
| goods_receipt | MK |
| supplier_invoice | AF |
| sales_return | SI |
| purchase_return | AI |
| transfer | TRF |
| stock_count | CNT |
| warehouse_slip | AMB |
| production_order | UE |
| collection | TH |
| payment | OD |
