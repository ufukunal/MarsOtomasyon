# 01 — Proje Kapsamı

## Amaç
MarsOtomasyon; cari, stok, satış, alış, finans ve operasyon süreçlerini tek veri omurgasında yöneten Türkçe ön muhasebe/operasyon uygulamasıdır.

## V1 ana kapsam
1. Core/Ayarlar
2. Cariler
3. Ürün/Stok/Depo
4. Satış
5. Alış
6. Kasa/Banka
7. Çek/Senet
8. Raporlar
9. Üretim
10. Fason
11. İade/RMA
12. E-Ticaret/Pazaryeri/B2B
13. İthalat/Konteyner
14. İletişim/API
15. Dosya/Yazdırma
16. Backup/Migrasyon/Sistem Sağlığı

## Core/Ayarlar
Firma, şube, kullanıcı, rol/yetki, numaralandırma, vergi, döviz, dönem, entegrasyon ayarları, audit.

## Cari
Müşteri/tedarikçi/karma cari, resmi bilgiler, iletişim, yetkililer, sevk/adres, risk limiti, cari iskonto, B2B erişimi, hareketler, bakiye ve ekstre.

## Ürün/Stok
Ürün, barkod, satış/alış fiyatı, depo/lokasyon, stok hareketleri, rezervasyon, transfer, sayım, görseller ve teknik dosyalar.

## Satış
Teklif, satış siparişi, irsaliye/sevkiyat, satış faturası, iade. Kısmi sevk ve kısmi faturalama zorunludur.

## Alış
Satınalma siparişi, mal kabul, alış faturası ve iade. Mal kabul fiziksel stok girişidir.

## Finans
Tahsilat, ödeme, gider, kasa/banka hareketleri, virman, ekstre import, mutabakat, çek/senet.

## Üretim/Fason
Basit reçete ve üretim akışı; fason malzeme gönderim/gelen mamul/fire/eksik takibi.

## İthalat
Konteyner/sevkiyat bazlı ürün dağılımı, koli ve malzeme eşleme, maliyet dağıtımı, üretim uyumluluk listesi, ürün fotoğraflı toplama/fason listeleri ve konteyner yükleme/ağırlık simülasyonu.

## E-Ticaret / Pazaryeri / B2B
Tek Integration Core üzerinden ilk kanal seti:
- WooCommerce
- Trendyol
- Hepsiburada
- Amazon SP-API
- n11
- PttAVM
- idefix
- Çiçeksepeti
- dahili Mars B2B

Ortak operasyon kapsamı provider capability'sine göre:
- ürün/listing ve kategori/özellik mapping
- stok
- fiyat
- sipariş
- sevkiyat/kargo
- iptal
- iade/talep
- fatura referansı/senkronizasyonu
- ürün/sipariş soruları
- provider settlement/muhasebe evidence where available
- entegrasyon problem merkezi

Bir provider'ın API'de sunmadığı özellik emüle edilmez; kanal capability matrix'i ile `supported / unsupported / manual` ayrımı yapılır.

## İletişim
SMS, e-posta, WhatsApp sağlayıcı adaptörleri; template, delivery, retry ve audit.

## Rapor
Hazır rapor merkezi, filtre/KPI/tablo, Excel/CSV/PDF/yazdırma ve zamanlanmış raporlar.

## V1 dışında
- SaaS abonelik/tier/billing
- Kubernetes/multi-region/hyperscale
- generic enterprise workflow platformu
- generic report designer
- tam MRP/QMS/OEE/ECO
- canlı open-banking
- ayrı search cluster
- gereksiz microservice parçalanması
- her pazaryeri için ayrı kopya sipariş/stok/fatura motoru

## Kullanıcı deneyimi sınırı
V16.3'te görünen ana ekran ve akışlar acceptance baseline'dır. Yeni pazaryerleri ayrı ana menü şişkinliği oluşturmaz; mevcut `E-Ticaret/B2B` çalışma alanına kanal filtresi/kartı olarak girer. Teknik mimari ayrıntıları normal kullanıcı arayüzüne sızdırılmaz.
