# G-201 — stock_movements ve stock_balances

**Veritabanı: DÖNEM.** Model `PeriodModel`'den türer, `company_id` kolonu
yoktur, migration `database/migrations/period/` altına yazılır.


## Amaç
Stok katmanının iki tablosu. Hareket gerçektir, bakiye türetilmiştir.

## Önkoşul
G-101 (lokasyonlar), G-106 (ürünler)


## Dokunulacak dosyalar
- stock_movements/stock_balances/product_costs period migration'ları
- StockMovement/StockBalance/ProductCost period modelleri
- immutable StockMovement guard/observer
- `app/Console/Commands/StockVerifyCommand.php`
- `tests/Feature/Stock/StockSchemaTest.php`


## Şema / Kod

Mevcut şema/kod örnekleri aşağıdaki kanonik stok sözleşmesiyle birlikte uygulanır; çelişkide kanonik sözleşme üstündür.

## Şemalar
`docs/01-veri-modeli/20-stock_movements.md`, `21-stock_balances.md` ve
`22-product_costs.md` içindeki şemaları **birebir** uygula.

## Referans

Kartlar aynı veritabanında olduğu için **gerçek yabancı anahtar** kullanılır:

```php
$table->foreignId('product_id')->constrained();
$table->foreignId('location_id')->constrained();
$table->string('product_code', 40);   // kopya — belge dökümü kolaylığı
```

Kod kopyası yine saklanır; kart adı değişse geçmiş hareket dökümü bozulmaz.

## Modeller
- `StockMovement` — ``. Değiştirilemez: `updating` ve
  `deleting` olaylarında istisna fırlat.
- `StockBalance` — ``. `available` hesaplanan özelliği:
  `quantity - reserved - consignment_reserved - quarantine`
- `ProductCost` — ``

## Doğrulama komutu

`php artisan stock:verify` — her ürün/lokasyon için hareket toplamıyla
bakiyeyi karşılaştırır, fark varsa listeler. Ayda bir çalıştırılır.

```php
$calculated = StockMovement::where('product_id', $p)->where('location_id', $l)
    ->selectRaw("SUM(CASE WHEN direction='in' THEN quantity ELSE -quantity END) as total")
    ->value('total');
```


## Kurallar

### Göreve özel kararlar
- stock_movements, stock_balances, product_costs period DB'dedir; bütün FK'ler period içidir.
- Actor kolonları Master users FK'si değildir.


### Uygulama ayrıntıları
- `stock_movements`, `stock_balances`, `product_costs` period DB'dedir; product/location ilişkileri aynı DB'de gerçek FK'dir.
- `stock_movements.quantity` her zaman temel birimde ve pozitif saklanır; yön ayrı `direction` alanıdır.
- `stock_balances` türetilmiş hızlı okuma tablosudur; business kodu doğrudan yazmaz.
- Master user actor alanları scalar ID + isim snapshot'tır; cross-DB FK kurulmaz.

## Kabul ölçütü
- Migration'lar çalışıyor, indeksler oluşuyor
- `StockMovement` güncellenmeye çalışılınca istisna fırlatıyor
- `available` doğru hesaplanıyor
- `stock:verify` boş veritabanında fark bulmuyor


## İstem
> stock_movements, stock_balances ve product_costs tabloları için migration
> ve modelleri yaz. Şemaları belirtilen dosyalardan birebir al.
> StockMovement değiştirilemez ve silinemez olsun. stock:verify artisan
> komutunu yaz.
