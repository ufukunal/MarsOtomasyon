# G-106 — Ürün kartı

**Veritabanı: DÖNEM.** Model `PeriodModel`'den türer, migration
`database/migrations/period/` altına yazılır. `company_id` kolonu YOKTUR.


## Amaç
Ürün kartı. **Her varyant ayrı karttır**; gruplama G-107'de gelir.

## Önkoşul
G-102 (birimler), G-103 (kategori/marka)


## Dokunulacak dosyalar
- `database/migrations/period/`

## Şema / Kod
`docs/01-veri-modeli/11-products.md` içindeki `products` şemasını birebir uygula.

## Model
- `use LogsActivity, HasAttachments;` — `SoftDeletes` YOK; ürün `is_active=false` ile pasife alınır.
- `kind`: `normal | set | configurable` (Enum `ProductKind`)
- `availableQuantity()` — Faz 2'de stok gelince dolar; şimdilik 0
- `setAvailability()` — set ürünse `min(bileşen/gerekli)`, değilse null

## Liste ekranı

Kolonlar: Kod · Ad (altında marka) · Kategori · Birim · Liste Fiyatı ·
KDV · Stok (Faz 2) · Durum
Filtreler: kategori, marka, tip (normal/set/konfigüre), aktif, stok durumu
Arama: kod, ad, barkod

**Maliyet kolonu `cost.view` izni yoksa tanıma hiç eklenmez.**

## Form ekranı

Bölüm **Genel**: kod, ad, açıklama, kategori, marka, birim, barkod
Bölüm **Fiyat**: liste fiyatı, KDV oranı, **KDV dahil/hariç seçici**
  (dahil seçilirse girilen değer hariçe çevrilip saklanır)
Bölüm **Stok**: negatif stok izni (ipucu: izinliyse uyarı verilir),
  minimum stok
Bölüm **Tip**: normal / set / konfigüre

## KDV dahil → hariç çevrimi

```php
// decimal string + BCMath/Money; PHP float YOK
$vatFactor = bcadd('1', bcdiv($vatRate, '100', 8), 8);
$excl = bcdiv($inclPrice, $vatFactor, 4);
```
Saklanan değer **her zaman hariçtir**.


## Kurallar

### Göreve özel kararlar
- Her varyant ayrı ürün kartıdır.
- `channel_stock_mode`: stock|production|manual alanı bulunur.
- `source_company_id` cross-DB FK değildir.
- Ürün kodu pasifleşse bile tekrar kullanılmaz; fiziksel/soft delete yoktur ve düzenleme `version` optimistic lock ile korunur.


### Uygulama ayrıntıları
- Her varyant ayrı product kartıdır; variant_group yalnız gruplama/sunum bilgisidir.
- `channel_stock_mode` değerleri `stock|production|manual` kararına uyar.
- Ürün kodu pasifleşse bile tekrar kullanılmaz; aynı şirket dönem devrinde ID+code korunur.
- `source_company_id + source_record_id` cross-company kopya provenance'ıdır; Master FK değildir.

## Kabul ölçütü
- Ürün açılıyor, kod benzersiz
- KDV dahil girilen fiyat BCMath/Money ile, float kullanmadan hariç olarak saklanıyor
- Stale `version` ile ikinci eşzamanlı düzenleme reddediliyor
- `cost.view` izni olmayan kullanıcıda maliyet kolonu **HTML çıktısında yok**
- Barkodla arama çalışıyor


## İstem
> products tablosu için migration, Product modeli, ProductKind enum'u,
> ProductList ve ProductForm bileşenlerini yaz. Şemayı 11-products.md'den
> birebir al. KDV dahil girilen fiyat Money/BCMath ile hariçe çevrilip saklansın; PHP float kullanma. SoftDeletes ekleme ve `version` optimistic lock uygula. Maliyet
> kolonu cost.view izni yoksa kolon tanımına EKLENMESİN.
