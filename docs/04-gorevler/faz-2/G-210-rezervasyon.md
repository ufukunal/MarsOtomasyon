# G-210 — Rezervasyon altyapısı

## Amaç
Siparişe bağlı stok rezervi. Faz 3'te sipariş ekranı bunu kullanacak.

## Önkoşul
G-202

## Şema

```php
// stock_reservations: id, company_id, product_id, location_id,
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
- Rezerve miktar fiziksel stoktan fazla olamaz (uyarı, engel değil —
  mal yolda olabilir)
- Sipariş iptal veya kapatılırsa rezerv otomatik çözülür
- Karantinadaki mal rezerve edilemez
- Rezerv **satır** bazındadır; sipariş satırında "rezerve edilsin mi"
  seçeneği vardır

## Ekran
Rezervasyonlar listesi: ürün, lokasyon, miktar, belge, tarih, durum.
Eylem: elle rezerv çözme (yetkiyle).

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
