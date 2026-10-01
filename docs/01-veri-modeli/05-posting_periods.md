# posting_periods

**Veritabanı: DÖNEM**

`company_id` kolonu **yoktur** — veritabanı zaten o şirkete ve yıla aittir.



## Amaç

Ay bazlı kayıt penceresi. Dönem kapatıldığında o aya kayıt girilemez ve
o aydaki belgeler değiştirilemez.

Gayri resmi sistemde arkada düzeltici bir defter olmadığı için bu kritik:
kapanmış ayın stok ve kasa rakamları sonradan oynamamalıdır.

## Şema

```php
Schema::connection('period')->create('posting_periods', function (Blueprint $table) {
    $table->id();
    $table->unsignedSmallInteger('year');
    $table->unsignedTinyInteger('month');        // 1-12
    $table->string('status', 10)->default('open');   // open | closed
    $table->foreignId('closed_by')->nullable()->constrained('users');
    $table->timestamp('closed_at')->nullable();
    $table->foreignId('reopened_by')->nullable()->constrained('users');
    $table->timestamp('reopened_at')->nullable();
    $table->text('reopen_reason')->nullable();
    $table->timestamps();

    $table->unique(['year', 'month'], 'posting_periods_unique');
});
```

## Kurallar

- Satırı olmayan ay **açık** sayılır (varsayılan açık)
- Kapalı döneme: yeni belge kesinleştirilemez, mevcut belge değiştirilemez,
  stok ve cari hareketi yazılamaz
- Yalnızca **Yönetici** dönemi yeniden açabilir; açma gerekçe ister ve
  `audit_log`'a düşer
- Kontrol tek noktadan yapılır: `EnsurePeriodOpen` action'ı
