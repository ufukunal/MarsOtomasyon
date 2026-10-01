# Faz 1 — Kartlar

**Tüm Faz 1 tabloları MASTER veritabanındadır.** Kartlar yıla bağlı değildir;
tüm dönemler aynı kartları kullanır. Modeller `PeriodModel`'den türer ve
`company_id` + global scope ile filtrelenir.

Cari ve ürün kartları, yan tabloları, lokasyonlar, fiyat listeleri ve
veri aktarımı. Faz 2'den (stok) sonraki her şey bu kartlara dayanır.

## Görevler

| No | Görev | Önkoşul |
|---|---|---|
| G-101 | Lokasyonlar (depo / şube / araç) | G-0b2 |
| G-102 | Birimler ve dönüşümler | G-0b2 |
| G-103 | Kategoriler ve markalar | G-0b2 |
| G-104 | Cari kartı — tablo, model, ekranlar | G-0b2, G-0b3 |
| G-105 | Cari yan tabloları (adres, yetkili, banka, kategori) | G-104 |
| G-106 | Ürün kartı — tablo, model, ekranlar | G-102, G-103 |
| G-107 | Varyant grupları ve varyant değerleri | G-106 |
| G-108 | Set ürün ve satılabilirlik hesabı | G-106 |
| G-109 | Konfigüratör tanımları | G-106 |
| G-110 | Fiyat listeleri | G-106 |
| G-111 | Şirketler arası kopyalama ekranı | G-104, G-106 |
| G-115 | **Satınalma talebi ve teklif toplama (basit)** | G-104, G-106 |
| G-112 | Excel / JSON içe aktarma | G-104, G-106 |
| G-113 | Ürün görsel setleri (Ortak, Trendyol, …) | G-106, G-009 |
| G-114 | Faz 1 testleri | hepsi |

## Bitiş ölçütü

- [ ] Cari ve ürün kartı açılıyor, aranıyor, düzenleniyor
- [ ] Her varyant ayrı kart, grup altında listeleniyor
- [ ] Set ürünün satılabilir adedi bileşenlerden hesaplanıyor
- [ ] Fiyat KDV hariç saklanıyor, girişte dahil/hariç seçilebiliyor
- [ ] Şirketler arası kopyalama izinle çalışıyor, izinsiz 403
- [ ] Excel ve JSON içe aktarma hatalı satırları raporluyor
- [ ] Görseller platform setleri halinde yükleniyor
- [ ] Tüm kart tabloları master'da, modeller `PeriodModel`'den türüyor
- [ ] Kart değişikliği tüm dönemlerde aynı anda görünüyor
- [ ] Tüm testler yeşil
- [ ] Kart tablolarında CHECK kısıtları tanımlı
