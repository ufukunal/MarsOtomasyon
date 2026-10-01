# stock_movements

**Veritabanı: DÖNEM**

`company_id` kolonu **yoktur** — veritabanı zaten o şirkete ve yıla aittir.



## Amaç

**Stoğu değiştiren tek gerçek kaynak.** Bakiye tablosu bundan türetilir;
hiçbir yerde stok elle güncellenmez.

Prototipte stok dağınık yerlerde tutuluyordu. Burada kural katı: stok
değişiyorsa bir `stock_movement` satırı vardır.

## Şema

```php
Schema::connection('period')->create('stock_movements', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->constrained();
    $table->foreignId('location_id')->constrained();

    $table->date('movement_date');
    $table->string('direction', 3);                   // in | out
    $table->string('reason', 30);                     // purchase, sale, transfer, count, production, return, scrap, opening
    $table->decimal('quantity', 18, 3);               // HER ZAMAN POZİTİF, yön direction'da
    $table->decimal('unit_cost', 18, 4);              // giriş: gerçek maliyet, çıkış: o anki ortalama
    $table->decimal('total_cost', 18, 4);             // quantity * unit_cost

    // hareketten SONRAKİ durum — geriye dönük hesap gerekmesin diye
    $table->decimal('balance_after', 18, 3);
    $table->decimal('avg_cost_after', 18, 4);

    $table->string('document_type', 40)->nullable();
    $table->unsignedBigInteger('document_id')->nullable();
    $table->string('document_no', 40)->nullable();

    $table->text('note')->nullable();
    $table->foreignId('created_by')->constrained('users');
    $table->timestamps();

    $table->index(['product_id','location_id','movement_date'], 'sm_product_loc_date');
    $table->index(['document_type','document_id'], 'sm_document');
});
```

## Kurallar

- `quantity` **her zaman pozitiftir**; yön `direction` alanındadır.
  Negatif miktar saklamak, toplamlarda işaret hatası kaynağıdır.
- Hareket **silinmez ve değiştirilmez.** Yanlışsa ters hareket yazılır.
- `balance_after` ve `avg_cost_after` hareket anında hesaplanıp saklanır.
  Böylece "1 Ocak'taki stok değeri neydi" sorusu tek sorguyla cevaplanır.
- Her hareket bir belgeye bağlıdır (`document_type` + `document_id`).
  Açılış bakiyesi istisnadır (`reason = opening`).
- Dönem kilidi kontrol edilir: kapalı aya hareket yazılamaz.

## Neden `balance_after` saklanıyor

Alternatif, her sorguda baştan toplamaktır. 100.000 hareketten sonra bu
yavaşlar ve geçmiş maliyet sorgusu imkânsızlaşır. Saklamanın maliyeti
iki kolon, getirisi her rapor.
