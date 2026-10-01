# G-110 — Fiyat listeleri

**Veritabanı: DÖNEM.** Model `PeriodModel`'den türer, migration
`database/migrations/period/` altına yazılır. `company_id` kolonu YOKTUR.


## Önkoşul
G-106 (ürün kartı)

## Dokunulacak dosyalar
- `database/migrations/period/`


## Şema / Kod

Bu bölümdeki mevcut şema örnekleri aşağıdaki kanonik mimari kurallarla birlikte uygulanır. Çelişkide kanonik kurallar üstündür.

## Amaç
Birden çok fiyat listesi (bayi, perakende, kampanya).

## Şemalar
```php
// price_lists:      id, name, currency, vat_included(bool),
//                   is_default(bool), is_active
// price_list_items: id, price_list_id, product_id,
//                   price decimal(18,4), valid_from(date,null), valid_to(date,null)
//   unique(price_list_id, product_id, valid_from)
```

## Kurallar
- **Fiyat her zaman KDV hariç saklanır.** `vat_included` yalnızca girişte
  nasıl yorumlanacağını belirler.
- Bir varsayılan liste olur
- Tarih aralığı boşsa süresizdir
- Aynı ürün için çakışan tarih aralığı olamaz
- Listede fiyatı olmayan ürün için `products.list_price` kullanılır

## Ekran
Liste: ad, para birimi, KDV dahil mi, varsayılan, ürün sayısı, durum.
Detay: ürün satırları tablosu, toplu fiyat güncelleme (yüzde artır/azalt),
Excel'den içe aktarma.


### Göreve özel kararlar
- Fiyat çözümleme: cari listesi → varsayılan liste → products.list_price → 0.
- %20+ fiyat sapması uyarı+audit; blok yok; prices.override zorunlu değil.
- DB'de fiyat KDV hariç saklanır.


### Uygulama ayrıntıları
- Fiyat listesi ve satırları period DB'dedir; ürün ilişkisi period içi gerçek FK'dir.
- Satış fiyatı çözüm sırası: cari fiyat listesi → varsayılan liste → `products.list_price` → 0.
- Fiyatlar DB'de KDV hariç tutulur.
- Liste fiyatından %20+ sapma blok değil uyarı + audit üretir; `prices.override` zorunluluğu yoktur.

## Kabul ölçütü
- KDV dahil işaretli listede girilen fiyat hariç olarak saklanıyor
- Çakışan tarih aralığı reddediliyor
- Toplu %10 zam tüm satırlara doğru uygulanıyor


## İstem
> price_lists ve price_list_items tabloları için migration, modeller,
> liste ve detay ekranlarını yaz. Fiyat her zaman KDV hariç saklansın.
> Toplu yüzde güncelleme eylemi ekle. Çakışan tarih aralığını engelle.
