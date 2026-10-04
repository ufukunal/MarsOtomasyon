# G-306 — İrsaliye ve kısmi sevk

## Amaç

Siparişten veya doğrudan sevk belgesi oluşturmak; fiziksel stok çıkışını ve rezerv tüketimini doğru lokasyonda gerçekleştirmek.

## Önkoşul

G-303, G-305, G-202, G-210.

## Dokunulacak dosyalar

- `app/Actions/Sales/CreateDispatchFromOrder.php`
- `app/Actions/Sales/PostDispatch.php`
- `app/Livewire/Sales/DispatchList.php`
- `app/Livewire/Sales/DispatchEditor.php`
- `tests/Feature/Sales/DispatchTest.php`


## Şema / Kod

Yeni tablo yok. `documents/document_lines/document_relations` ile Faz 2 `stock_reservations` ve `stock_movements` kullanılır. Dispatch satırı source order line'a `source_line_id` ile bağlanır.

## Ekran

v65:

- list: Sevk No, Sipariş, Cari, Depo, Tarih, Toplama, Paket, Kargo, Takip, Teslim, Durum
- tabs: Hareketler, Bilgiler, Toplama, Paketler, Kargo, Dosyalar, Timeline
- eylemler: Kaydet, Toplamaya Başla, Post; detayda Topla, Paketle, Kargoya Teslim, Reverse

`Pre-Shipment QC` kalite modülü kapsam dışı olduğu için eklenmez.

## Siparişten oluşturma

Kullanıcı kaynak order line ve sevk miktarını seçer.

Her satır için:

```
sevk_edilebilir =
  ordered
- cancelled
- önceki posted dispatch
- aynı order satırından doğrudan posted invoice
```

Miktar aşılırsa işlem engellenir.

Rezervasyon birden fazla lokasyondaysa dispatch satırları gerçek çıkış lokasyonlarına göre bölünebilir.

`CreateDispatchFromOrder` başlık seviyesinde `order_to_dispatch` relation yazar.

Child dispatch line:

- `source_line_id = order_line.id`
- `location_id = rezerv/çıkış lokasyonu`
- quantity/base_quantity source birim snapshot'ıyla

## Doğrudan irsaliye

v65 `dispatch_new` ekranı doğrudan sevke izin verir. Kaynak sipariş yoksa:

- contact zorunlu,
- product/unit/location zorunlu,
- source_line_id null,
- rezerv tüketimi yok,
- posting stok çıkışı üretir,
- cari hareket üretmez.

## Post

`PostDispatch` G-303 zincirini dispatch profiliyle çağırır:

1. period açık,
2. numara,
3. satır bazında **tek** `RecordStockMovement(out, reason=dispatch)`,
4. kaynak rezerv varsa ilgili miktar kadar `ConsumeReservation`; bu adım ikinci stock movement yazmaz,
5. posted status/actor,
6. verify,
7. audit.

**Cari hareket yoktur.**

## Kısmi sevk

Kaynak order quantity değiştirilmez. Birden çok dispatch aynı order line'a bağlanabilir.

Sevk sonrası order:

- kalan > 0 ise açık/confirmed,
- kalan 0 ise ilişkili workflow'a göre closed olabilir.

Kalan iptal davranışı G-305'tedir.


## Kurallar

- İrsaliye stok çıkışı üretir, cari etkilemez.
- Kaynak kalan miktar aşılamaz.
- Sipariş kaynaklı sevkte `order_to_dispatch` relation zorunludur.
- Rezerv tüketimi ile stok çıkışı aynı transaction zincirindedir fakat iki ayrı sorumluluktur: stock out yalnız `RecordStockMovement`, reservation state yalnız `ConsumeReservation`.
- Kalite modülü eklenmez.

## Kabul ölçütü

- 100 siparişten 40 + 30 sevk edilebiliyor, kalan 30 doğru.
- Toplam child sevk 100'ü aşamıyor.
- Reserved lokasyondan sevkte reservation azalıyor ve physical stock düşüyor.
- Direct dispatch stok düşürüyor, cari yazmıyor.
- Dispatch posting tekrarında idempotency çift stock movement üretmiyor.
- Kapanmış dönemde post engelleniyor.
- Kalite modülü ekran/route eklenmiyor.

## İstem

> İrsaliye ekranı ile CreateDispatchFromOrder/PostDispatch action'larını uygula. İrsaliye stok çıkışı yapsın ama cari hareket yazmasın. source_line_id ile kısmi sevki takip et; miktarın kaynak kalanını aşmasına izin verme. QC modülü ekleme.
