# G-009 — Dosya ekleri

## Amaç
Her belgeye ve karta dosya eklenebilmesi. **Sıkıştırma yapılmaz.**

## Önkoşul
G-003

## Şema

```php
Schema::create('attachments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained();
    $table->morphs('attachable');
    $table->string('disk', 30)->default('attachments');
    $table->string('path');
    $table->string('original_name');
    $table->string('mime', 100);
    $table->unsignedBigInteger('size');
    $table->string('collection', 40)->nullable();
    $table->unsignedSmallInteger('sort_order')->default(0);
    $table->foreignId('uploaded_by')->constrained('users');
    $table->timestamps();
    $table->index(['company_id','attachable_type','attachable_id'], 'attachments_owner_index');
});
```

## HasAttachments trait

```php
trait HasAttachments
{
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->orderBy('sort_order');
    }
}
```

## Kurallar
- Dosyaya **her zaman** `Storage::disk($attachment->disk)` üzerinden erişilir.
  Kodda sabit yol yazılmaz. Disk dolunca `.env` değişir, S3 uyumlu servise
  (iDrive, Hetzner) geçilir, kod değişmez.
- İzin verilen türler: jpg, jpeg, png, webp, pdf, xlsx, csv, docx
- Boyut sınırı 25 MB (`config('attachments.max_size')`)
- `collection` ürün görsellerinde platform setini tutar
  (Ortak, Trendyol, Hepsiburada, N11, Site-A)
- Kayıt silinince dosya da silinir (observer)

## Kabul ölçütü
- Dosya yüklenir, `attachments` diskine yazılır, kayıt oluşur
- İzinsiz tür reddedilir
- Kayıt silinince dosya diskten kalkar
- Farklı şirketin eki listede görünmez

## İstem
> attachments tablosu için migration, Attachment modeli, HasAttachments
> trait'i, AttachmentObserver (silmede dosyayı kaldırsın) ve
> config/attachments.php dosyasını yaz. Sıkıştırma veya yeniden boyutlandırma
> KOD YAZMA. Dosya erişimi her zaman disk üzerinden olsun.
