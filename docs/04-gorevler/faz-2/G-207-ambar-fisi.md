# G-207 — Ambar fişi

**Veritabanı: DÖNEM.** Model `PeriodModel`'den türer, `company_id` kolonu
yoktur, migration `database/migrations/period/` altına yazılır.


## Amaç
Belge bağı olmadan yapılan stok giriş/çıkışı: fire, numune verme,
demirbaş çıkışı, düzeltme.

## Önkoşul
G-202


## Dokunulacak dosyalar
- warehouse_slips/warehouse_slip_lines period migration'ları
- WarehouseSlip/Line period modelleri
- ambar fişi list/detail Livewire + blade
- PostWarehouseSlip/ReverseWarehouseSlip Action'ları
- `tests/Feature/Stock/WarehouseSlipTest.php`

## Şema / Kod
```php
// warehouse_slips: id, number, location_id, slip_date,
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


### Göreve özel kararlar
- Koli etiketi ambar fişi kolilerine bağlıdır.
- `1/4` benzeri sıra toplam koli sayısından üretilir.
- Etiket ölçüsü Master print_profile width_mm/height_mm ile çözülür.


### Uygulama ayrıntıları
- Ambar fişi period DB'de depo içi fiziksel işlem belgesidir.
- Koli etiketleri ambar fişindeki koli kayıtlarından üretilir; satış irsaliyesine bağlı değildir.
- `1/N` sıra bilgisi gerçek koli sayısından hesaplanır.
- Yazdırma profili Master `print_profiles` üzerinden çözülür; ölçü width_mm/height_mm ile gelir.

## Kabul ölçütü
- Çıkış fişi stoğu azaltıyor, hareket yazıyor
- Çıkışta maliyet ortalamadan geliyor
- Kesinleşen fiş değiştirilemiyor
- Kapalı döneme fiş kesinleştirilemiyor


## İstem
> warehouse_slips ve warehouse_slip_lines tabloları için migration,
> modeller, ekranlar ve kesinleştirme action'ını yaz. Kesinleştirme
> RecordStockMovement'i çağırsın. Çıkışta birim maliyet girilemesin.
