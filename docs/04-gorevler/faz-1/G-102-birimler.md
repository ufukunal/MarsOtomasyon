# G-102 — Birimler ve dönüşümler

**Veritabanı: DÖNEM.** Model `PeriodModel`'den türer, migration
`database/migrations/period/` altına yazılır. `company_id` kolonu YOKTUR.


## Önkoşul
G-0b2 (tablo bileşeni)

## Dokunulacak dosyalar
- `database/migrations/period/`

## Amaç
Adet, kutu, kg gibi birimler ve aralarındaki dönüşüm katsayıları.
Kristal gibi kalemler kg alınıp adet satılabilir.

## Şema / Kod
```php
// units: id, code(20), name, is_base(bool), is_active, version
// unit_conversions: id, from_unit_id, to_unit_id, factor decimal(18,6), version
//   unique(from_unit_id, to_unit_id)
```

## Kurallar
- Her şirkette en az bir temel birim (`ADET`) olmalı
- Dönüşüm tek yönlü tanımlanır; ters yön `bcdiv('1', factor, 6)` ile decimal string olarak hesaplanır. PHP float/bölme kullanılmaz.
- Katsayı sıfır veya negatif olamaz
- Kullanılan birim silinemez

## Seed
ADET (temel), KUTU, KOLİ, KG, METRE, SET


### Göreve özel kararlar
- Stok hareketleri her zaman ürünün temel birimindedir.
- Dönüşüm yoksa katsayı 1 varsayılmaz; işlem engellenir.
- `unit_conversions.factor` decimal(18,6).


### Uygulama ayrıntıları
- `units` ve `unit_conversions` period DB'dedir.
- Dönüşüm katsayısı `decimal(18,6)` saklanır; eksik dönüşümde factor=1 varsayılmaz.
- Stok hareketi temel birimde yazılacağı için bu görev dönüşüm çözümlemesinin tek kaynağını oluşturur.
- Aynı şirket dönem devrinde birim ve dönüşüm kimlikleri korunur.

## Kabul ölçütü
- 1 KUTU = 6 ADET tanımlanınca ters yön 0,166667 olarak çalışıyor
- Sıfır katsayı reddediliyor
- Ters dönüşüm BCMath ile 6 hane üretiliyor; float kullanılmıyor
- Stale `version` ile eşzamanlı güncelleme reddediliyor


## İstem
> units ve unit_conversions tabloları için migration, modeller, seeder ve
> ekranları yaz. Ters dönüşümü BCMath ile hesapla; PHP float kullanma. Katsayı pozitif ve düzenlenebilir kayıtlar `version` optimistic lock korumalı olsun.
