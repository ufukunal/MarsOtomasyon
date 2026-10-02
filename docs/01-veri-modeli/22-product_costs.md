# product_costs

**Veritabanı: DÖNEM**

`company_id` kolonu **yoktur** — veritabanı zaten o şirkete ve yıla aittir.



## Amaç

Ürünün maliyet bilgileri tek yerde. Dört ayrı değer tutulur ki hangi
rakamın nereden geldiği görülebilsin.

## Şema

```php
Schema::connection('period')->create('product_costs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->constrained();

    $table->decimal('last_purchase_price', 18, 4)->default(0);  // son alış
    $table->decimal('moving_average', 18, 4)->default(0);       // GEÇERLİ MALİYET
    $table->decimal('import_cost', 18, 4)->default(0);          // ithalat dosyasından (Faz 7)
    $table->decimal('production_cost', 18, 4)->default(0);      // üretimden (Faz 8)

    $table->timestamp('last_purchase_at')->nullable();
    $table->timestamps();
    $table->unique(['product_id']);
});
```

## Geçerli maliyet

**`moving_average` geçerli maliyettir.** Stok değeri, kârlılık ve çıkış
hareketlerinin birim maliyeti bu alandan okunur.

Diğer üçü bilgi amaçlıdır; hangi rakamın neden farklı olduğunu görmek için.

K-124 gereği `import_cost`, ürünün son finalized ithalat dosyasındaki **final import unit cost** snapshot'ıdır. Faz 7 ek ithalat maliyetinin moving_average'a yansıma formülü A-053 kapandığında kanonikleşecektir.

## Güncelleme

Yalnızca giriş hareketinde (`direction = in`, `reason = purchase|production|opening`)
güncellenir. Çıkışta değişmez.

Transfer maliyeti değiştirmez — mal yer değiştirir, maliyet aynı kalır.
