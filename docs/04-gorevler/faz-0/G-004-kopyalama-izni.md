# G-004 — Şirketler arası kopyalama izni

**Veritabanı: MASTER**

## Amaç
Şirketler arası veri kopyalama izni. **Kayıt yoksa izin yoktur.**

## Önkoşul
G-003


## Dokunulacak dosyalar
- Bu görev için mevcut metinde tanımlanan uygulama/migration/test dosyaları; kapsam dışı dosyaya dokunma.

## Şema / Kod
```php
Schema::connection('master')->create('company_copy_permissions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('source_company_id')->constrained('companies');
    $table->foreignId('target_company_id')->constrained('companies');
    $table->string('type', 20);                 // contact | product
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->unique(['source_company_id','target_company_id','type'], 'ccp_unique');
});
```

## Enum

```php
enum CompanyCopyPermissionType: string
{
    case Contact = 'contact';
    case Product = 'product';
}
```

## Model kuralları
- `CompanyCopyPermission` modeli — `MasterModel`'den türer (master tablosu)
  (bu tablo iki şirketi birden ilgilendirir)
- Doğrulama: `source_company_id !== target_company_id`
- `sourceCompany()` ve `targetCompany()` ilişkileri

## Yardımcı

```php
public static function allows(int $sourceId, int $targetId, CompanyCopyPermissionType $type): bool
{
    return static::query()
        ->where('source_company_id', $sourceId)
        ->where('target_company_id', $targetId)
        ->where('type', $type->value)
        ->where('is_active', true)
        ->exists();
}
```


## Kurallar

**Faz bağlamı:** Faz 0 — altyapı; sonraki fazların sözleşmesini bozmamalı.

### Bu göreve özel kanonik notlar
- company_copy_permissions Master DB'de ayrı tablodur; kaynak→hedef şirket iznini tutar.
- Kopyalamanın kendisi period_source üzerinden aynı yıl DB'leri arasında yapılır.


### Uygulama ayrıntıları
- `company_copy_permissions` Master DB'de kaynak şirket, hedef şirket ve izin türünü tutar.
- Bu görev kart kopyalamayı yapmaz; yalnız hangi kaynak→hedef akışına izin verildiğini modeller.
- Permission modeli MasterModel kullanır; şirket global scope'u yoktur.
- Kaynak ve hedef aynı şirket olamaz kuralı migration CHECK veya Action doğrulamasıyla açıkça korunur.

## Kabul ölçütü
- İzin yokken `allows()` false döner
- Aynı üçlü ikinci kez eklenemez (unique hatası)
- `source === target` kaydı reddedilir


## İstem
> company_copy_permissions tablosu için migration, CompanyCopyPermissionType enum'u ve CompanyCopyPermission
> modelini yaz. Model `MasterModel`'den türesin; şirket global scope/BelongsToCompany kullanmasın. allows() statik
> yardımcısını ekle. source ve target aynı olamaz kuralını modelde doğrula.
