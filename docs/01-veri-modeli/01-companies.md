# companies

## Amaç

Tam izole tüzel birim. Resmi ve gayri resmi işleyiş **ayrı birer şirkettir**.
Her şirketin kendi stoğu, carisi, kasası, belge serileri ve kullanıcı
yetkileri vardır.

## Şema

```php
Schema::create('companies', function (Blueprint $table) {
    $table->id();
    $table->string('code', 20)->unique();          // MARS, MARS2
    $table->string('name');                         // kısa ad
    $table->string('legal_name')->nullable();       // resmi unvan
    $table->string('tax_office')->nullable();
    $table->string('tax_number', 20)->nullable();
    $table->text('address')->nullable();
    $table->string('city', 60)->nullable();
    $table->string('phone', 30)->nullable();
    $table->string('email')->nullable();
    $table->string('logo_path')->nullable();

    // varsayılanlar
    $table->unsignedSmallInteger('default_term_days')->default(30);
    $table->decimal('cost_deviation_threshold', 7, 4)->default(25);  // %25
    $table->char('base_currency', 3)->default('TRY');

    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

## Kısıtlar

- `code` benzersiz, büyük harf, değiştirilemez (kayıt sonrası salt okunur)
- `default_term_days` cari kartında boş bırakılırsa kullanılır
- `cost_deviation_threshold` alış fiyatı sapma uyarısının eşiği (bkz.
  `02-is-kurallari/03-maliyet.md`)
- `base_currency` her zaman `TRY`; döviz yalnız ithalat/alış belgelerinde

## İlişkiler

- `hasMany` → neredeyse tüm iş tabloları
- `hasMany` → `company_links` (kaynak ve hedef olarak)

## Örnek veri (seed)

| code | name | legal_name | default_term_days |
|---|---|---|---|
| MARS | Mars Aydınlatma | Mars Aydınlatma San. Tic. A.Ş. | 30 |
| MARS2 | Mars Ticaret | Mars Ticaret Ltd. Şti. | 30 |
