# stock_balances

**Veritabanı: DÖNEM**

`company_id` kolonu **yoktur** — veritabanı zaten o şirkete ve yıla aittir.



## Amaç

Anlık bakiye özeti. `stock_movements` toplamından **türetilir**, bağımsız
gerçek değildir. Hızlı okuma içindir.

## Şema

```php
Schema::connection('period')->create('stock_balances', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->constrained();
    $table->foreignId('location_id')->constrained();

    $table->decimal('quantity', 18, 3)->default(0);            // fiziksel stok
    $table->decimal('reserved', 18, 3)->default(0);            // sipariş rezervi
    $table->decimal('consignment_reserved', 18, 3)->default(0);// numune/konsinye
    $table->decimal('quarantine', 18, 3)->default(0);          // iade/kontrol bekleyen

    $table->timestamps();
    $table->unique(['product_id','location_id'], 'stock_balances_unique');
});
```

## Hesaplanan alan

```
kullanılabilir = quantity − reserved − consignment_reserved − quarantine
```

Bu kolon **saklanmaz**, okurken hesaplanır.

## Güncelleme

Yalnızca `RecordStockMovement` action'ı günceller. Başka hiçbir yerden
`stock_balances` yazılmaz.

Tutarlılık kontrolü: `php artisan stock:verify` komutu hareket toplamıyla
bakiyeyi karşılaştırır, fark varsa raporlar. Ayda bir çalıştırılır.

## Rezerve, konsinye ve karantina

| Alan | Stoktan düşer mi | Kullanılabiliri azaltır mı |
|---|---|---|
| `reserved` | Hayır | Evet |
| `consignment_reserved` | Hayır | Evet |
| `quarantine` | Hayır | Evet |

Üçü de fiziksel stoğun içindedir; sadece satılamaz durumdadır.
