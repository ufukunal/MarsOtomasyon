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

## Güncelleme sahipliği

`stock_balances` tek bir bağımsız gerçek kaynak değildir; alanlar kendi kaynak kayıtlarının hızlı özetidir. Bu nedenle **kolon bazında tek-yazar** kuralı uygulanır:

- `quantity` → yalnız `RecordStockMovement`; kaynağı `stock_movements`,
- `reserved` → yalnız `ReserveStock / ReleaseReservation / ConsumeReservation`; kaynağı aktif `stock_reservations`,
- `quarantine` → yalnız karantina Action'ları; kaynağı aktif karantina kayıtları,
- `consignment_reserved` → yalnız konsinye/numune Action'ları; ilgili modülün kaynak kayıtları.

Başka Action/controller/Livewire kodu bu alanlara doğrudan update yapmaz. Her sahip Action ilgili `product_id + location_id` stock_balance satırını transaction içinde kilitler.

Tutarlılık: `integrity:stock` quantity'yi stock_movements ile; `integrity:reservations` reserved'ı aktif reservation toplamıyla; `integrity:quarantine` quarantine değerini aktif karantina toplamıyla karşılaştırır. Fark raporlanır, otomatik düzeltilmez.

## Rezerve, konsinye ve karantina

| Alan | Stoktan düşer mi | Kullanılabiliri azaltır mı |
|---|---|---|
| `reserved` | Hayır | Evet |
| `consignment_reserved` | Hayır | Evet |
| `quarantine` | Hayır | Evet |

Üçü de fiziksel stoğun içindedir; sadece satılamaz durumdadır.
