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
Tek Integration Core üzerinden **V1 doğrulanmış kanal seti**:
- WooCommerce
- Trendyol
- Hepsiburada
- Amazon SP-API
- n11
- PttAVM
- idefix
- Allesgo
- dahili Mars B2B

**Doğrulama bekleyen / sonraya bırakılan adaylar:**
- Çiçeksepeti
- Pazarama
- Koçtaş
- Teknosa
- Temu Türkiye
- Boyner

Bu adaylar güncel resmî API dokümanı veya gerçek seller/partner erişimi doğrulanmadan V1 teslim kapsamına girmez.

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

# Planlı V1 sonrası genişlemeler
Aşağıdaki özellikler artık fikir/adayı değil, resmî post-V1 roadmap kapsamıdır. V1 go-live M24'ü bloklamaz; varsayılan olarak M25–M31 arasında uygulanır.

1. **Product Family / Variant** — mevcut Product/SKU authority'sini bozmadan ürün ailesi ve marketplace parent/child grouping.
2. **Barkod / Termal Etiket** — A4/termal/ZPL ürün, depo, lokasyon, koli ve sevkiyat etiketleri.
3. **Mobil Depo / Scanner** — mobil/PWA üzerinden kabul, toplama, sevk, transfer, sayım ve fason tarama operasyonları.
4. **Kargo API Adapterları** — shipment create/cancel, label, tracking ve return-shipment capability'leri.
5. **OCR Fatura / Dekont Okuma** — attachment extraction + confidence + human review + normal domain use-case.
6. **Hafif CRM** — lead, fırsat, aktivite, takip ve teklif/cari bağlantısı; finans authority değil.
7. **BI Export** — curated read-model dataset, scheduled export ve kontrollü analitik erişim; write-back yok.

Ayrıntılı kapsam ve dependency: `28_PLANLI_GENISLEMELER.md`.

## V1 dışında / planlı olmayan
- SaaS abonelik/tier/billing
- Kubernetes/multi-region/hyperscale
- generic enterprise workflow platformu
- generic report designer
- tam MRP/QMS/OEE/ECO
- canlı open-banking
- ayrı search cluster
- gereksiz microservice parçalanması
- her pazaryeri için ayrı kopya sipariş/stok/fatura motoru
- doğrulanmamış marketplace API'sine dayalı production adapter

## Kullanıcı deneyimi sınırı
V16.3'te görünen ana ekran ve akışlar acceptance baseline'dır. Yeni pazaryerleri ayrı ana menü şişkinliği oluşturmaz; mevcut `E-Ticaret/B2B` çalışma alanına kanal filtresi/kartı olarak girer. Planlı genişlemeler de mümkün olduğunca mevcut `Ürün/Stok`, `Satış`, `Cariler`, `Raporlar` ve `Ayarlar` çalışma alanlarına secondary surface olarak eklenir; top-level navigasyon yalnız yeni onaylı UI sözleşmesiyle değişir. Teknik mimari ayrıntıları normal kullanıcı arayüzüne sızdırılmaz.
