# G-310 — Araçtan sıcak satış

## Amaç

Araç lokasyonunu depo gibi kullanarak merkezden araca transfer ve araç stokundan doğrudan satış faturası akışını kurmak.

## Önkoşul

G-206, G-307, G-309, locations.kind=vehicle.

## Dokunulacak dosyalar

- `app/Actions/Sales/StartVehicleHotSale.php`
- `app/Livewire/Sales/VehicleHotSale.php`
- `tests/Feature/Sales/VehicleHotSaleTest.php`

## Akış

K-014:

```
Merkez depo
  -> G-206 transfer
  -> araç lokasyonu
  -> doğrudan satış faturası
  -> araç stok çıkışı + cari debit
```

Tahsilat otomatik oluşturulmaz; gerekiyorsa G-309 tahsilat ekranından ayrıca girilir.

## Araç seçimi

Yalnız `locations.kind = vehicle` ve aktif lokasyonlar seçilebilir.

Araçta ürün satılabilmesi için fiziksel stok araç location'da bulunmalıdır; ürün `allow_negative_stock` true ise mevcut G-202 negatif stok kuralı geçerlidir.

## Transfer

Sıcak satış ekranı merkez→araç stok taşımayı yeniden yazmaz. G-206 transfer action'ını kullanır.

Mal `in_transit` iken araçta satılabilir stok sayılmaz. Araç teslim aldığında hedef stok artar.

## Fatura

VehicleHotSale fatura oluştururken:

- document_type = sales_invoice
- line.location_id = vehicle location
- source_line_id = null, doğrudan satış
- G-302 hesap
- G-307 / G-303 direct invoice posting

sonucunda:

- araç stock out,
- contact transaction debit.

## UI

Hızlı akış:

1. Araç
2. Cari
3. Ürün/miktar
4. fiyat/KDV
5. Faturayı oluştur
6. gerekirse ayrı Tahsilat eylemi

Yeni özel stok veya cari tablosu yoktur.

## Kabul ölçütü

- Transfer teslim edilmeden araç stokunda ürün görünmüyor.
- Teslim sonrası vehicle balance artıyor.
- Sıcak satış faturası yalnız vehicle location'dan stok düşürüyor.
- Cari debit faturayla oluşuyor.
- Tahsilat otomatik oluşmuyor.
- Aynı satış idempotency tekrarında çift fatura/stock movement yok.
- Araç olmayan location bu ekranda seçilemiyor.

## İstem

> Araç sıcak satış akışını yeni stok sistemi kurmadan G-206 transfer + G-307 direct invoice üzerine kur. Vehicle location'dan stok düşür ve cari borcu faturada oluştur. Tahsilatı otomatik bağlama; G-309 ayrı akış olarak kalsın.
