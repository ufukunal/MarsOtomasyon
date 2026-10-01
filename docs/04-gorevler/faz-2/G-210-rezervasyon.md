# G-210 — Rezervasyon altyapısı

**Veritabanı: DÖNEM.** Model `PeriodModel`'den türer, `company_id` kolonu
yoktur, migration `database/migrations/period/` altına yazılır.


## Amaç
Siparişe bağlı stok rezervi. Faz 3'te sipariş ekranı bunu kullanacak.

## Önkoşul
G-202


## Dokunulacak dosyalar
- `database/migrations/period/`

## Şema / Kod
```php
// stock_reservations: id, product_id, location_id,
//   quantity decimal(18,3),
//   document_type(40), document_id, document_line_id,
//   status(active|released|consumed),
//   created_by, created_at, released_at
```

## Action'lar

`ReserveStock` — rezerv oluşturur, `stock_balances.reserved` artırır
`ReleaseReservation` — rezervi çözer, `reserved` azaltır
`ConsumeReservation` — sevkiyatta çağrılır: rezerv çözülür **ve**
  çıkış hareketi yazılır (tek transaction)

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
- Consume rezerv çözümü; sevkiyat stok çıkışı ile aynı transaction zincirinde.


### Uygulama ayrıntıları
- Rezervasyon yalnız mevcut kullanılabilir stok kadar oluşturulur; karşılanamayan miktar siparişte açık kalır.
- Aynı sipariş satırı birden fazla lokasyona ayrı reservation kayıtlarıyla dağıtılabilir.
- Rezervasyon fiziksel quantity'yi düşürmez; reserved alanını artırır.
- İrsaliye/sevk sırasında ilgili rezervler çözülür ve fiziksel stok çıkışı aynı iş akışında yazılır.

## Kabul ölçütü
- Rezerv sonrası kullanılabilir azalıyor, stok değişmiyor
- Rezerv çözülünce kullanılabilir geri geliyor
- Sevkiyatta rezerv çözülüyor ve stok düşüyor, ikisi tek transaction
- Karantinadaki mal rezerve edilemiyor


## İstem
> stock_reservations tablosu için migration, model, ReserveStock,
> ReleaseReservation ve ConsumeReservation action'larını ve rezervasyon
> listesi ekranını yaz. ConsumeReservation rezerv çözme ve stok çıkışını
> tek transaction içinde yapsın.
