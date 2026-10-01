# G-004 — Şirketler arası kopyalama izni

**Veritabanı: MASTER**

## Amaç
Şirketler arası veri kopyalama izni. **Kayıt yoksa izin yoktur.**

## Önkoşul
G-003

## Şema

```php
Schema::create('company_copy_permissions', function (Blueprint $table) {
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

## Kabul ölçütü
- İzin yokken `allows()` false döner
- Aynı üçlü ikinci kez eklenemez (unique hatası)
- `source === target` kaydı reddedilir

## İstem
> company_copy_permissions tablosu için migration, CompanyCopyPermissionType enum'u ve CompanyCopyPermission
> modelini yaz. Model BelongsToCompany trait'ini KULLANMASIN. allows() statik
> yardımcısını ekle. source ve target aynı olamaz kuralını modelde doğrula.
