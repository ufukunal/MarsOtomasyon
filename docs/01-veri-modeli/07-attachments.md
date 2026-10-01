# attachments

**Veritabanı: DÖNEM**

Kart ve dönem belgelerine ait ekler period DB'dedir. `company_id` yoktur.

## Şema

```php
Schema::connection('period')->create('attachments', function (Blueprint $table) {
    $table->id();
    $table->morphs('attachable');
    $table->string('disk', 30)->default('attachments');
    $table->string('path');
    $table->string('original_name');
    $table->string('mime', 100);
    $table->unsignedBigInteger('size');
    $table->string('collection', 40)->nullable();
    $table->unsignedSmallInteger('sort_order')->default(0);

    // Master users'a FK yok
    $table->unsignedBigInteger('uploaded_by')->nullable();
    $table->string('uploaded_by_name')->nullable();

    $table->timestamps();
    $table->index(['attachable_type','attachable_id']);
});
```

## Güvenlik

MIME uzantıdan değil dosya içeriğinden doğrulanır. Fiziksel ad UUID'dir. SVG yasaktır. İzin verilen temel tipler jpg/jpeg/png/webp/pdf/xlsx/csv/docx; limit varsayılan 25 MB ve sistem ayarından değişebilir.

Dosya yolu disk soyutlaması üzerinden çözülür. Sıkıştırma yapılmaz. Ürün görsel setlerinde `collection` kanal setini tutabilir.

Kart eki dönem devrinde kartla birlikte kopyalanır. Dosyanın fiziksel taşıma/kopyalama işlemi `integrity:files` ile doğrulanır.
