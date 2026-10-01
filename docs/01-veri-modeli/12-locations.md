# locations

**Veritabanı: DÖNEM**

## Şema

```php
Schema::connection('period')->create('locations', function (Blueprint $table) {
    $table->id();
    $table->string('code', 20)->unique();
    $table->string('name');
    $table->string('kind', 10); // warehouse | branch | vehicle
    $table->string('plate', 20)->nullable();
    $table->text('address')->nullable();
    $table->boolean('is_default')->default(false);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

`company_id` yoktur. Kod pasifleşse de tekrar kullanılmaz. Aynı şirket dönem devrinde ID+code korunur.

Satış document_line lokasyon taşıyabilir. Rezervasyon motoru gerekirse bir satırı birden fazla lokasyondaki `stock_reservations` kayıtlarına dağıtır.

Araç normal lokasyon gibi stok tutar. Sıcak satışta araca transfer edilir, araçtan doğrudan fatura kesildiğinde stok araç lokasyonundan ve cari fatura ile etkilenir.
