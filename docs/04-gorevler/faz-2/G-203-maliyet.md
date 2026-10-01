# G-203 — Hareketli ortalama ve sapma uyarısı

**Veritabanı: DÖNEM.** Model `PeriodModel`'den türer, `company_id` kolonu
yoktur, migration `database/migrations/period/` altına yazılır.


## Amaç
Tek maliyet yöntemi. Geçerli maliyet `product_costs.moving_average`.

## Önkoşul
G-202

## Formül

```
yeni_ortalama = (mevcut_miktar × mevcut_ortalama + giren_miktar × giren_fiyat)
                ÷ (mevcut_miktar + giren_miktar)
```

`mevcut_miktar` **tüm lokasyonların toplamıdır** — maliyet ürün bazındadır,
lokasyon bazında değil.

## Kenar durumlar

| Durum | Davranış |
|---|---|
| Stok 0 veya negatif, giriş var | Giren fiyat doğrudan maliyet olur |
| Çıkış | Ortalama değişmez, çıkış maliyeti o anki ortalama |
| Transfer | Ortalama değişmez, iki harekete de aynı maliyet |
| İade (satış) | Ortalama değişmez, karantinaya çıkıştaki maliyetle girer |
| Üretim mamul girişi | Malzeme maliyeti + fason bedeli / üretilen adet (Faz 8) |

## Sapma uyarısı

Alış girişinde:

```php
$threshold = $product->category?->cost_deviation_threshold
          ?? $company->cost_deviation_threshold;          // varsayılan 25

$avg = $cost->moving_average;

if ($avg > 0) {
    $deviation = abs(($unitCost - $avg) / $avg) * 100;
    if ($deviation > $threshold) {
        // ekranda onay iste, devam edilirse activity_log'a yaz
    }
}
```

Uyarı metni: *"{kod} ürününün ortalama maliyeti {avg}, girilen fiyat
{fiyat} (%{sapma} {yüksek/düşük}). Devam edilsin mi?"*

**Engel değil, uyarıdır.** Amaç hatalı giriş yakalamak, iş durdurmak değil.

## Kabul ölçütü
- 10 adet × 100 ₺ sonra 10 adet × 200 ₺ → ortalama 150 ₺
- Çıkış sonrası ortalama 150 ₺ kalır
- Stok 0'ken 5 adet × 300 ₺ → ortalama 300 ₺
- %30 sapmada uyarı çıkar, %20'de çıkmaz
- Uyarı geçilince `activity_log`'a düşer
- Transfer sonrası ortalama değişmez

## İstem
> UpdateMovingAverage action'ını ve sapma uyarısı kontrolünü yaz.
> Mevcut miktar tüm lokasyonların toplamı olsun. Stok sıfır veya negatifken
> giren fiyatı doğrudan maliyet yap. Uyarı engelleyici olmasın, geçilince
> activity_log'a yazsın.
