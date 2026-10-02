# locations

**Veritabanı: DÖNEM**

## Şema

```php
Schema::connection('period')->create('locations', function (Blueprint $table) {
    $table->id();
    $table->string('code', 20)->unique();
    $table->string('name');
    $table->string('kind', 20); // warehouse | branch | vehicle | subcontractor
    $table->string('plate', 20)->nullable();
    $table->foreignId('subcontractor_contact_id')->nullable()->constrained('contacts')->restrictOnDelete();
    $table->text('address')->nullable();
    $table->boolean('is_default')->default(false);
    $table->boolean('is_active')->default(true);
    $table->unsignedInteger('version')->default(1);
    $table->timestamps();
});
```

`company_id` yoktur. Kod pasifleşse de tekrar kullanılmaz; fiziksel silme/SoftDeletes yoktur ve düzenleme `version` optimistic lock ile korunur. Aynı şirket dönem devrinde ID+code korunur.

Satış document_line lokasyon taşıyabilir. Rezervasyon motoru gerekirse bir satırı birden fazla lokasyondaki `stock_reservations` kayıtlarına dağıtır.

Araç normal lokasyon gibi stok tutar. Sıcak satışta araca transfer edilir, araçtan doğrudan fatura kesildiğinde stok araç lokasyonundan ve cari fatura ile etkilenir.


## Faz 8 fason genişletmesi

K-148…K-150:

- `kind=subcontractor` fasoncu lokasyonudur.
- `subcontractor_contact_id` zorunludur.
- Fasoncu normal supplier/contact kartıdır; ayrı fasoncu kart ailesi yoktur.
- Subcontractor location normal satış rezervasyon/sevk taramasına girmez.
- Fasona gönderilen mal şirket mülkiyetinde fiziksel stok olarak bu location'da izlenir.
- Dönem devrinde kart ve fiziksel stok location bazında taşınır.
