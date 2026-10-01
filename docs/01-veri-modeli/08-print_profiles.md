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
