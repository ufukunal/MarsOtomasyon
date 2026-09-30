# print_profiles

## Amaç

"Hangi iş hangi yazıcıya gitsin" ayarı. Logo'daki davranışın karşılığı.

Tarayıcı, sisteme bağlı yazıcıları göremez; bu yüzden ilk aşamada profil
**şablonu ve kağıt boyutunu** belirler. İleride özel tarayıcı kabuğu veya
yerel ajan devreye girince aynı profil **gerçek cihaza** bağlanır.
Bu yüzden tablo şimdiden kurulur.

## Şema

```php
Schema::create('print_profiles', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained();
    $table->foreignId('user_id')->nullable()->constrained();   // null = şirket varsayılanı
    $table->string('machine_key', 64)->nullable();             // tarayıcıya yazılan makine kimliği
    $table->string('print_type', 30);       // a4 | product_label | carton_label | receipt | report
    $table->string('printer_name')->nullable();
    $table->string('paper_size', 20)->nullable();              // A4, 100x150, 80mm
    $table->unsignedBigInteger('template_id')->nullable();     // ileride belge şablonu
    $table->timestamps();

    $table->unique(['company_id','user_id','machine_key','print_type'], 'print_profiles_unique');
});
```

## Çözümleme sırası

Yazdırma anında profil şu sırayla aranır:

1. Şirket + kullanıcı + makine + tip
2. Şirket + kullanıcı + tip (makine farketmez)
3. Şirket + tip (şirket varsayılanı)
4. Hiçbiri yoksa sistem varsayılanı

## Yazdırma tipleri

| Tip | Kullanım | Çıktı |
|---|---|---|
| `a4` | Fatura, irsaliye, teklif | PDF |
| `product_label` | Ürün barkod etiketi | ZPL (ileride) |
| `carton_label` | Ambar koli etiketi (1/4, 3/5) | ZPL (ileride) |
| `receipt` | Sevkiyat fişi, depo çıkışı | ESC/POS (ileride) |
| `report` | Rapor dökümü | PDF |
