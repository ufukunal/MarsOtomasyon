# G-207 — Ambar fişi

## Amaç
Belge bağı olmadan yapılan stok giriş/çıkışı: fire, numune verme,
demirbaş çıkışı, düzeltme.

## Önkoşul
G-202

## Şema

```php
// warehouse_slips: id, company_id, number, location_id, slip_date,
//   direction(in|out), reason(30), status(draft|posted|cancelled),
//   note, created_by, posted_by, posted_at
// warehouse_slip_lines: id, warehouse_slip_id, product_id,
//   quantity decimal(18,3), unit_cost decimal(18,4)->nullable, note
```

## Sebepler
Giriş: buluntu, düzeltme, numune iadesi, açılış
Çıkış: fire, kırık, numune verme, demirbaş, düzeltme

## Kurallar
- Kesinleştirilince `RecordStockMovement` çağrılır (`reason = adjustment`)
- Çıkışta birim maliyet o anki ortalamadır, girilemez
- Girişte birim maliyet girilebilir; girilmezse o anki ortalama kullanılır
- Girişte fiyat girilirse sapma uyarısı çalışır
- Kesinleşen fiş değiştirilemez, ters fişle iptal edilir

## Ekran
Liste: numara, tarih, lokasyon, yön, sebep, satır sayısı, durum.
Detay: ürün seçici (barkod okuyucu desteği), miktar, not.

## Kabul ölçütü
- Çıkış fişi stoğu azaltıyor, hareket yazıyor
- Çıkışta maliyet ortalamadan geliyor
- Kesinleşen fiş değiştirilemiyor
- Kapalı döneme fiş kesinleştirilemiyor

## İstem
> warehouse_slips ve warehouse_slip_lines tabloları için migration,
> modeller, ekranlar ve kesinleştirme action'ını yaz. Kesinleştirme
> RecordStockMovement'i çağırsın. Çıkışta birim maliyet girilemesin.
