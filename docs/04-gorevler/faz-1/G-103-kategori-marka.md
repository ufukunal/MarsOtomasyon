# G-103 — Kategoriler ve markalar

**Veritabanı: DÖNEM.** Model `PeriodModel`'den türer, migration
`database/migrations/period/` altına yazılır. `company_id` kolonu YOKTUR.


## Amaç
Ürün kategorileri (ağaç yapısı) ve markalar.

## Şema
```php
// product_categories: id, company_id, parent_id(nullable, self), name, sort_order, is_active
// brands:             id, company_id, name, is_active
```

## Kurallar
- Kategori ağacı en fazla 3 seviye
- Alt kategorisi olan kategori silinemez
- Ürünü olan kategori/marka silinemez, pasife alınır
- Kategori adı aynı seviyede benzersiz

## Ekran
Kategori: ağaç görünümü, sürükleyerek sıralama yerine `sort_order` alanı.
Marka: düz liste.

## Kabul ölçütü
- Üç seviye açılıyor, dördüncü reddediliyor
- Dolu kategori silinemiyor

## İstem
> product_categories ve brands tabloları için migration, modeller ve
> ekranları yaz. Kategori ağacı en fazla 3 seviye olsun, kontrol et.
