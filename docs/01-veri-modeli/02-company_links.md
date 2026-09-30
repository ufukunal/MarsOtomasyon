# company_links

## Amaç

Şirketler **tam izoledir**. Aralarındaki tek bağ, izinli veri kopyalamadır.
Bu tablo hangi şirketin hangi şirketten hangi türde veri kopyalayabileceğini
tutar. **Kayıt yoksa izin yoktur** — diğer şirketin verisi hiçbir ekranda
görünmez, aranamaz.

Dış bir firmaya kurulum verildiğinde o şirket için satır açılmaz, hiçbir
şeyi göremez.

## Şema

```php
Schema::create('company_links', function (Blueprint $table) {
    $table->id();
    $table->foreignId('source_company_id')->constrained('companies');  // veriyi VEREN
    $table->foreignId('target_company_id')->constrained('companies');  // veriyi ALAN
    $table->string('type', 20);                 // contact | product
    $table->boolean('is_active')->default(true);
    $table->timestamps();

    $table->unique(['source_company_id', 'target_company_id', 'type'], 'company_links_unique');
});
```

## Kısıtlar

- `source_company_id != target_company_id` (model tarafında doğrulanır)
- `type` yalnızca `contact` veya `product` (Enum: `CompanyLinkType`)
- İzin **tek yönlüdür**. A → B izni, B → A iznini vermez.

## Kopyalama davranışı

Kopyalama **yeni kayıt** oluşturur, canlı bağ kurmaz:

- Hedefte yeni `contact` / `product` satırı açılır
- `source_company_id` ve `source_record_id` doldurulur
- Kaynak sonradan değişirse hedef **değişmez**; "kaynakta değişti mi"
  kontrolü ayrı bir işlemdir
- Kaynak şirket kurulumdan çıkarsa hedefteki veri çalışmaya devam eder

## İlişkiler

- `belongsTo` → `companies` (source ve target)
