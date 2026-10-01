# G-102 — Birimler ve dönüşümler

**Veritabanı: DÖNEM.** Model `PeriodModel`'den türer, migration
`database/migrations/period/` altına yazılır. `company_id` kolonu YOKTUR.


## Amaç
Adet, kutu, kg gibi birimler ve aralarındaki dönüşüm katsayıları.
Kristal gibi kalemler kg alınıp adet satılabilir.

## Şema
```php
// units: id, company_id, code(20), name, is_base(bool), is_active
// unit_conversions: id, company_id, from_unit_id, to_unit_id, factor decimal(18,6)
//   unique(company_id, from_unit_id, to_unit_id)
```

## Kurallar
- Her şirkette en az bir temel birim (`ADET`) olmalı
- Dönüşüm tek yönlü tanımlanır, ters yön otomatik hesaplanır (1/factor)
- Katsayı sıfır veya negatif olamaz
- Kullanılan birim silinemez

## Seed
ADET (temel), KUTU, KOLİ, KG, METRE, SET

## Kabul ölçütü
- 1 KUTU = 6 ADET tanımlanınca ters yön 0,166667 olarak çalışıyor
- Sıfır katsayı reddediliyor

## İstem
> units ve unit_conversions tabloları için migration, modeller, seeder ve
> ekranları yaz. Ters dönüşüm otomatik hesaplansın. Katsayı pozitif olsun.
