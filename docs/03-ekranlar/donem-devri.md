# Ekran — Dönem Devri

**Veritabanı: HER İKİSİ.** Kaynak dönemden okur, hedef dönem veritabanını
oluşturur ve yazar; `periods` kaydı master'dadır.

## Rota
`/ayarlar/donem-devri`

## Yetki
Yalnızca `Yönetici`.

## Ekran bölümleri

**Üst göstergeler:** kaynak dönem, hedef dönem, taşınacak stok kalemi
sayısı, taşınacak cari sayısı.

**Devir öncesi kontrol listesi** — her biri Uygun / Dikkat rozetiyle:

| Kontrol | Neden |
|---|---|
| Kapanmamış ay | Tüm aylar kapalı olmalı |
| Açık sipariş / teklif | Devirde **taşınmaz**, kullanıcı bilmeli |
| Yolda transfer | Çıkmış ama girmemiş mal bakiyeyi bozar |
| Karantinada bekleyen | Karar verilmemiş kalemler |
| Negatif stok | Devirden önce düzeltilmeli |
| Kesinleşmemiş belge | Taslaklar taşınmaz |

**Dikkat** çıkması devri engellemez; kullanıcı bilerek devam edebilir.
Engelleyen tek durum: hedef veritabanının zaten var olması.

## Devir adımları

```
1. CREATE DATABASE {db_prefix}_{yeni yıl} + migration
2. Stok açılışı — her ürün/lokasyon için giriş hareketi
   reason = opening, birim maliyet = KAPANIŞ HAREKETLİ ORTALAMASI
3. product_costs kopyalanır (maliyet sürekliliği)
4. Cari bakiyeleri açılış fişi olarak yazılır
5. Kasa ve banka bakiyeleri açılış olarak yazılır
6. Vadesi gelmemiş çek ve senetler taşınır
7. Kaynak dönem status = closed
8. periods satırı: carried_from_period_id, carried_at doldurulur
```

**Kartlar taşınmaz** — cari, ürün, fiyat listesi master'dadır, yeni
dönem aynı kartları kullanır.

## Maliyet sürekliliği — en kritik nokta

Kapanış hareketli ortalaması, açılış hareketinin birim maliyeti olur.
Aksi halde 1 Ocak'ta maliyet sıfırlanır ve o yılın tüm kârlılık
hesapları yanlış çıkar.

## Geri alma

Devir geri alınabilir: hedef veritabanı silinir, `periods` satırı
kaldırılır, kaynak dönem yeniden `active` yapılır.

**Ancak** yeni döneme kayıt girildiyse geri alma veri kaybıdır.
Ekran bu durumu kontrol eder ve uyarır.

## Önizleme
"Önizleme Al" hiçbir şey yazmadan ne taşınacağını listeler:
ürün sayısı, toplam miktar, toplam stok değeri, cari sayısı, toplam bakiye.
