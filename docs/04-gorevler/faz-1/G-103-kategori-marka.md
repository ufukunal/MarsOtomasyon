# G-103 — Kategoriler ve markalar

**Veritabanı: DÖNEM.** Model `PeriodModel`'den türer, migration
`database/migrations/period/` altına yazılır. `company_id` kolonu YOKTUR.


## Önkoşul
G-0b2 (tablo bileşeni)

## Dokunulacak dosyalar
- `database/migrations/period/`

## Amaç
Ürün kategorileri (ağaç yapısı) ve markalar.

## Şema / Kod
```php
// product_categories: id, parent_id(nullable, self), name, sort_order, is_active
// brands:             id, name, is_active
```

## Kurallar
- Kategori ağacı en fazla 3 seviye
- Alt kategorisi olan kategori silinemez
- Ürünü olan kategori/marka silinemez, pasife alınır
- Kategori adı aynı seviyede benzersiz

## Ekran
Kategori: ağaç görünümü, sürükleyerek sıralama yerine `sort_order` alanı.
Marka: düz liste.


### Göreve özel kararlar
- Kategori/marka period kartıdır; company_id yok.
- Kategori self-FK aynı period içinde gerçek FK.


### Uygulama ayrıntıları
- Kategori ve marka period kartıdır; company_id yoktur.
- Kategori parent ilişkisi aynı period içinde self-FK ile kurulur.
- Kod/ad benzersizliği görevde tanımlanan kapsamda period DB constraint ile korunur.
- Dönem devrinde kullanılan kategori/marka kimlikleri korunur.

## Kabul ölçütü
- Üç seviye açılıyor, dördüncü reddediliyor
- Dolu kategori silinemiyor


## İstem
> product_categories ve brands tabloları için migration, modeller ve
> ekranları yaz. Kategori ağacı en fazla 3 seviye olsun, kontrol et.
