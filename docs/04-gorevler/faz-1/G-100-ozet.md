# Faz 1 — Kartlar

**Tüm Faz 1 işletme kartları PERIOD veritabanındadır.** company_id/global scope/BelongsToCompany yoktur. Aynı period içindeki ilişkiler gerçek FK kullanır.

## Görevler

G-101 lokasyonlar · G-102 birimler/dönüşümler · G-103 kategori/marka · G-104 cari · G-105 cari yan tabloları · G-106 ürün · G-107 varyant · G-108 set · G-109 konfigüratör · G-110 fiyat listeleri · G-111 şirketler arası kopyalama · G-112 import · G-113 görsel setleri · G-114 test · G-115 basit satınalma talebi/teklif toplama.

## Bitiş ölçütü

- [ ] Bütün kart migration'ları period altında ve company_id'siz
- [ ] source_company_id cross-DB FK değil
- [ ] cari/ürün/lokasyon/birim/kategori/varyant/set/fiyat ilişkileri period FK
- [ ] pasif kart kodu tekrar kullanılamıyor
- [ ] aynı şirket dönem devrinde kart ID+kod korunabiliyor
- [ ] şirketler arası kopyalamada hedef yeni ID oluşturuyor
- [ ] kod çakışmasında otomatik overwrite/-2 yok; kullanıcı seçim yapıyor
- [ ] company_copy_permissions Master'da
- [ ] kaynak aynı yıl period_source bağlantısından okunuyor
- [ ] ürün channel_stock_mode stock|production|manual
- [ ] konfigüratör fiyat alanı içermiyor
- [ ] set stok miktarı bileşen kullanılabilirliklerinden türetiliyor
- [ ] fiyat KDV hariç saklanıyor
- [ ] %20+ satış fiyat sapması blok değil uyarı+audit
- [ ] import kısmi sessiz başarı bırakmıyor
- [ ] görsel upload MIME/UUID/SVG kurallarına uyuyor
- [ ] gerçek PostgreSQL izolasyon ve devir contract testleri geçiyor
