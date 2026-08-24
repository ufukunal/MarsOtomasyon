# 08 — Modüller Master Planı V4

Bu dosya **ürün/modül sahipliğini** tanımlar. Geliştirme sırası ve küçük dikey teslimler `16_UYGULAMA_SIRASI_MILESTONE.md` tarafından belirlenir. Kullanıcı-visible bilgi mimarisi `26_V16_3_TASARIM_UYUMU.md` ile aynıdır.

## 1. Ana Sayfa
- özet KPI
- son işlemler
- uyarılar
- hızlı erişim
- yapılacaklar/takvim gerektiği ölçüde

Dashboard business authority değildir.

## 2. Cariler
- Cari Listesi
- Cari Detay readonly
- Cari Düzenle
- Cari Hareketleri / Ekstre
- Firma / Ticari
- İletişim / Yetkililer
- Sevk / Adres + manuel Ambar/Nakliye
- Banka Bilgileri
- Risk Limiti / Cari İskontosu
- B2B / Bayi Erişimi
- Notlar / Dosyalar

Cari authority `account_transactions`; OpenItem yoktur.

## 3. Ürün / Stok
- Ürünler
- Kategoriler
- Barkod/QR
- Stok Durumu
- Stok Hareketleri
- Depolar / Lokasyonlar
- Rezervasyonlar
- Depo Transferleri
- Stok Sayımı / Quick Count
- Teknik Bilgi Dosyası
- Kurulum Kılavuzu
- Tedarikçiler
- Görseller + kanal/site destination'ları

Tek satış + tek alış fiyatı. Lot/seri V1 core yok.

## 4. Satış
- Teklifler
- Satış Siparişleri
- İrsaliye / Sevkiyat
- Satış Faturaları
- Proforma
- Satış İadeleri

Yeni belge listeden açılır. Kısmi sevk/faturalama first-class'tır.

## 5. Alış
- Satınalma Siparişleri
- Mal Kabul
- Alış Faturaları
- Alış İadeleri

Mal Kabul physical stock IN authority'sidir ve basit `Uygun / Kontrol Bekliyor / Uygun Değil` kararı taşır.

## 6. Üretim
- Üretim Emirleri
- Reçeteler
- Malzeme Çıkışları
- Mamul Girişleri
- Teknik Üretim Dosyaları
- Üretim Raporu

Akış basittir; routing/work-center/ECO/OEE yoktur.

## 7. Fason
Üretim alanıyla ilişkili fakat ayrı operasyon akışı:
- fason emir/takip
- gönderilen malzeme
- gelen mamul
- fire/eksik
- kalan/custody
- teknik/fotoğraflı talimatlar

Ayrı stok authority yoktur.

## 8. Kasa / Banka
Ana menü V16.3'e göre yalnız:
- Tahsilat
- Ödeme
- Gider
- Kasa Hareketleri
- Banka Hareketleri
- Virman
- Ekstre İçe Aktar

Secondary screens:
- Kasalar
- Kasa Sayımı
- Banka Hesapları
- Banka Mutabakatı

ilgili hareket ekranından açılır.

## 9. Çek / Senet
- Alınan Çekler
- Verilen Çekler
- Alınan Senetler
- Verilen Senetler
- Portföy / Konum / Hareket History
- Ön/arka görsel/tarama

## 10. İadeler / RMA
Ortak İade Merkezi:
- satış iadesi
- alış iadesi
- e-ticaret/RMA

Stock ve finance effect aynı source lineage'da, kendi authority'lerinde yürür.

## 11. İthalat
- İthalat Dosyaları / Shipment
- Konteynerler
- Ürün/Koli/Component Eşleşmesi
- Material Location
- Maliyet Analizi
- Üretim/Toplama/Fason Listeleri
- Teknik Dosya/Görseller
- Yükleme/Ağırlık-Boyut Simülatörü

## 12. E-Ticaret / B2B
- Kanal Merkezi
- E-Ticaret Siparişleri
- Ürün Entegrasyonu
- E-Ticaret İadeleri
- Ürün / Sipariş Soruları
- Fatura Entegrasyonu
- Entegrasyon Sorunları
- Kanal Ayarları

Tek Integration Core + WooCommerce/Trendyol/Mars B2B adapterları.

## 13. İletişim
- E-Posta
- SMS
- WhatsApp
- şablonlar
- delivery/provider attempts
- müşteri iletişim operasyonları

Credential config `Ayarlar → Entegrasyonlar` altındadır.

## 14. Raporlar
- Rapor Merkezi
- Kaydedilmiş Raporlar
- Zamanlanmış Raporlar
- Excel/CSV/PDF/Yazdır

Hedef yaklaşık 40 hazır rapor / 8 kategori. Generic report designer yoktur.

## 15. Ayarlar
- Firma / Sistem
- Şubeler
- Kullanıcılar / Roller / Yetkiler
- Numaralandırma
- Vergi / Para Birimi / Kur
- Dönemler
- Entegrasyonlar
- Yedekleme
- gerekli business settings

## 16. Dosyalar / Yazdırma
Ortak Files capability:
- attachment/media metadata
- private authorization
- checksum/version
- scan/quarantine where enabled

Belge PDF'leri server-owned versioned template kullanır. Ürün Kurulum PDF Builder domain-specific'tir.

## 17. Sistem Sağlığı / Backup / Migrasyon
Admin/operasyon capability:
- health
- failed jobs/outbox
- backup runs
- restore runs/drills
- recovery mode
- import/export jobs
- migration/go-live tools

Normal kullanıcı menüsünde teknik state/jargon şişkinliği oluşturulmaz.

## Modül anti-goals
- aynı iş için duplicate ekran yok
- her liste için ayrı teknik modül yok
- provider/queue/internal state normal kullanıcı menüsüne çıkmaz
- generic ERP/QMS/PLM menu yok
- SaaS billing/tier yok
- Kubernetes/multi-region/hyperscale yok
- ayrı search daemon yok
- generic report designer yok
- core lot/seri ve çoklu fiyat listesi yok
