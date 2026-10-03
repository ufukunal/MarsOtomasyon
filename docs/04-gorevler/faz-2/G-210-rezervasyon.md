# G-210 — Rezervasyon altyapısı

**Veritabanı: DÖNEM.** Model `PeriodModel`'den türer, `company_id` kolonu
yoktur, migration `database/migrations/period/` altına yazılır.


## Amaç
Siparişe bağlı stok rezervi. Faz 3'te sipariş ekranı bunu kullanacak.

## Önkoşul
G-202


## Dokunulacak dosyalar
- stock_reservations period migration/model
- ReserveStock/ReleaseReservation/ConsumeReservation Action'ları
- reservation list Livewire + blade
- reservation integrity helper
- `tests/Feature/Stock/ReservationTest.php`

## Şema / Kod
```php
// stock_reservations: id, product_id, location_id,
//   quantity decimal(18,3),
//   document_type(40), document_id, document_line_id,
//   status(active|released|consumed), version,
//   created_by, created_by_name, created_at, released_at
// product/location/document/document_line aynı period DB'de gerçek FK
```

## Action'lar

`ReserveStock` — rezerv oluşturur, `stock_balances.reserved` artırır
`ReleaseReservation` — rezervi çözer, `reserved` azaltır
`ConsumeReservation` — sevkiyatta çağrılır: yalnız rezerv kaydını `consumed` yapar ve `stock_balances.reserved` miktarını azaltır. **Stok hareketi yazmaz.** Fiziksel çıkışın tek yazma noktası `RecordStockMovement`dır; çağıran posting Action ikisini aynı transaction içinde yürütür.

## Kurallar
- Rezerve stoktan **düşmez**, kullanılabiliri azaltır
- Rezervasyon yalnız kullanılabilir stok kadar oluşturulur; kalan açık kalır
- Sipariş iptal veya kapatılırsa rezerv otomatik çözülür
- Karantinadaki mal rezerve edilemez
- Rezerv **satır** bazındadır; sipariş satırında "rezerve edilsin mi"
  seçeneği vardır

## Ekran
Rezervasyonlar listesi: ürün, lokasyon, miktar, belge, tarih, durum.
Eylem: elle rezerv çözme (yetkiyle).


### Göreve özel kararlar
- Rezervasyon fiziksel stoktan fazla **oluşturulmaz**; kalan açık kalır.
- Motor lokasyonları tarayıp aynı sipariş satırını birden çok reservation kaydına bölebilir.
- Consume rezerv çözümü yalnız reservation state/reserved bakiyesini değiştirir; stok çıkışı `RecordStockMovement` tarafından aynı transaction zincirinde ayrı adımda yazılır.


### Uygulama ayrıntıları
- Rezervasyon yalnız mevcut kullanılabilir stok kadar oluşturulur; karşılanamayan miktar siparişte açık kalır.
- Aynı sipariş satırı birden fazla lokasyona ayrı reservation kayıtlarıyla dağıtılabilir.
- Rezervasyon fiziksel quantity'yi düşürmez; reserved alanını artırır.
- İrsaliye/sevk sırasında fiziksel stok çıkışı `RecordStockMovement` ile yazılır; ardından ilgili rezervler `ConsumeReservation` ile çözülür. `ConsumeReservation` ikinci stock movement üretmez.

## Kabul ölçütü
- Rezerv sonrası kullanılabilir azalıyor, stok değişmiyor
- Rezerv çözülünce kullanılabilir geri geliyor
- Sevkiyatta tek `RecordStockMovement(out)` oluşuyor; rezerv ayrıca consumed oluyor ve reserved azalıyor, ikisi tek transaction
- Karantinadaki mal rezerve edilemiyor


## İstem
> stock_reservations tablosu için migration, model, ReserveStock,
> ReleaseReservation ve ConsumeReservation action'larını ve rezervasyon
> listesi ekranını yaz. `ConsumeReservation` stok hareketi yazmasın; yalnız rezerv state/reserved miktarını güncellesin. Fiziksel çıkışı çağıran posting akışı `RecordStockMovement` ile tek kez yazsın; ikisi aynı transaction içinde yürüsün.
