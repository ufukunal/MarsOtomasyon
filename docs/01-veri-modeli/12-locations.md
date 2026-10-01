# locations

**Veritabanı: DÖNEM**

`company_id` kolonu **yoktur** — veritabanı zaten o şirkete ve yıla aittir.



## Amaç
Stok tutulan yer. Üç tip: **depo, şube, araç**.

Araç sıcak satışta kullanılır: merkezden araca transfer yapılır, satış
araç deposundan düşer, gün sonunda kalan geri transferle döner.

## Şema

```php
Schema::connection('period')->create('locations', function (Blueprint $table) {
    $table->id();
    $table->string('code', 20);
    $table->string('name');
    $table->string('kind', 10);              // warehouse | branch | vehicle
    $table->string('plate', 20)->nullable(); // araç için
    $table->text('address')->nullable();
    $table->boolean('is_default')->default(false);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->unique(['company_id','code']);
});
```

## Kurallar
- Her şirkette en az bir `warehouse` ve bir varsayılan lokasyon olmalı
- Araç lokasyonu depo gibi davranır; ayrı bir mantık yoktur
- Fason lokasyonu da `warehouse` tipindedir, adı ile ayrılır (Faz 8)
