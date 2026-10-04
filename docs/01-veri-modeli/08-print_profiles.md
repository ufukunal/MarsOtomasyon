# print_profiles

**Veritabanı: MASTER**

Yazdırma profili yıllık işletme verisi değildir. Şirket + kullanıcı + makine + çıktı tipi bazında Master'da tutulur ve aynı şirketin bütün dönemlerinde kullanılabilir.

## Şema

```php
Schema::connection('master')->create('print_profiles', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->string('machine_key', 64)->nullable();
    $table->string('print_type', 30);
    $table->string('printer_name')->nullable();

    $table->string('paper_code', 20)->nullable(); // A4 | custom vb.
    $table->decimal('width_mm', 8, 2)->nullable();
    $table->decimal('height_mm', 8, 2)->nullable();

    $table->unsignedBigInteger('template_id')->nullable();
    $table->jsonb('settings')->nullable(); // dpi, gap, darkness vb. taşıyıcı/cihaz özel
    $table->unsignedInteger('version')->default(1);
    $table->timestamps();

    $table->unique(
        ['company_id','user_id','machine_key','print_type'],
        'print_profiles_unique'
    );
});
```

## Çözümleme

1. şirket + kullanıcı + makine + tip
2. şirket + kullanıcı + tip
3. şirket + tip
4. sistem varsayılanı

## Kurallar

- K-061: marka/model bağımsız; sabit Zebra/TSC şartı yok.
- K-066: temel etiket ölçüsü string parse edilmez; `width_mm` ve `height_mm` kullanılır.
- `printer_name` yalnız fiziksel cihaz eşlemesi.
- ZPL/ESC-POS/PDF gibi taşıyıcı ayrıntısı `PrintManager` arkasındadır.

- Profil düzenleme `version` optimistic lock ile korunur.


## Faz 10 template ilişkisi

K-214/K-215 gereği `template_id` Master `document_templates` kaydını işaret eder. Aynı company kapsamındadır. Faz 10 migration'ı `document_templates` oluşturulduktan sonra `print_profiles.template_id -> document_templates.id` gerçek FK'sini ekler; mevcut Faz 0 migration sırası geriye dönük bozulmaz.

Profile üzerindeki template seçimi, ilgili print type varsayılan template'ini override edebilir. Final render sırasında kullanılan template revision print job provenance'ında ayrıca snapshot edilir; profile kaydı geçmiş çıktının tek kanıtı değildir.
