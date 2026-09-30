# attachments

## Amaç

Her belgeye ve karta dosya eklenebilir: fatura taraması, ürün görseli,
teknik föy, fotoğraf.

**Sıkıştırma yapılmaz** — kullanıcı dosyayı kendisi optimize eder (karar
günlüğü K-012).

## Şema

```php
Schema::create('attachments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained();
    $table->morphs('attachable');                    // attachable_type + attachable_id
    $table->string('disk', 30)->default('attachments');
    $table->string('path');
    $table->string('original_name');
    $table->string('mime', 100);
    $table->unsignedBigInteger('size');
    $table->string('collection', 40)->nullable();    // görsel seti adı (Trendyol, Ortak...)
    $table->unsignedSmallInteger('sort_order')->default(0);
    $table->foreignId('uploaded_by')->constrained('users');
    $table->timestamps();

    $table->index(['company_id', 'attachable_type', 'attachable_id'], 'attachments_owner_index');
});
```

## Kurallar

- Dosya erişimi **her zaman** `disk` üzerinden yapılır; kodda sabit yol yazılmaz.
  Disk dolduğunda `.env` değişir, S3 uyumlu servise (iDrive, Hetzner) geçilir,
  kodda hiçbir şey değişmez.
- İzin verilen türler: jpg, jpeg, png, webp, pdf, xlsx, csv, docx
- Boyut sınırı: varsayılan 25 MB, şirket ayarından değiştirilebilir
- `collection` alanı ürün görsellerinde platform setini tutar
  (Ortak, Trendyol, Hepsiburada, N11, Site-A)
- Silme: kayıt silinince dosya da silinir (model observer)
