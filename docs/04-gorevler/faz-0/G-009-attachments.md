# G-009 — Dosya ekleri

## Amaç
Her belgeye ve karta dosya eklenebilmesi. **Sıkıştırma yapılmaz.**

## Önkoşul
G-003


## Dokunulacak dosyalar
- Bu görev için mevcut metinde tanımlanan uygulama/migration/test dosyaları; kapsam dışı dosyaya dokunma.

## Şema / Kod
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
    $table->unsignedBigInteger('uploaded_by')->nullable();
    $table->string('uploaded_by_name')->nullable();
    $table->timestamps();
    $table->index(['attachable_type','attachable_id'], 'attachments_owner_index');
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


**Faz bağlamı:** Faz 0 — altyapı; sonraki fazların sözleşmesini bozmamalı.

### Bu göreve özel kanonik notlar
- Kart ekleri period DB'dedir; uploaded_by Master user'a FK değildir.
- MIME içerikten doğrula, UUID fiziksel ad, SVG yasak, integrity:files ekle.


### Uygulama ayrıntıları
- `attachments` period DB'dedir; attachable morph ilişkisi aynı period içindeki kart/belgelere gider.
- `uploaded_by` Master user scalar ID + isim snapshot'tır; cross-DB FK yoktur.
- Dosya türü uzantıdan değil içerikten doğrulanır; fiziksel ad UUID'dir ve SVG reddedilir.
- Kart eki dönem devrinde ilgili kartla taşınacağı için dosya kopyalama sonucu `integrity:files` ile doğrulanır.

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
