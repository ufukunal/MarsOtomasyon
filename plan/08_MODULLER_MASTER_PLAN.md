# 08 — Modüller Master Planı

> Bu dosya `MarsEski` V16.3 plan setinden `MarsOtomasyon` uygulama reposuna taşınmıştır. Plan otoritesi `plan/README.md` ve `26_V16_3_TASARIM_UYUMU.md` ile birlikte okunur.

## Geliştirme sırası

0. CI + Core + UI Shell
1. Ayarlar / Firma / Kullanıcı / Yetki
2. Cariler
3. Ürün / Stok / Depo
4. Satış — Teklif → Sipariş → İrsaliye/Sevkiyat → Fatura
5. Alış — Satınalma Siparişi → Mal Kabul → Alış Faturası
6. Kasa / Banka — Tahsilat → Ödeme → Gider → Virman → Ekstre
7. Çek / Senet
8. Raporlar
9. Üretim
10. Fason
11. İadeler / RMA
12. E-Ticaret / Pazaryeri / B2B
13. İthalat / Konteyner
14. Kalite gereken noktalarda operasyonel kontrol
15. Haberleşme / API / Entegrasyonlar
16. Dosyalar / Yazdırma Merkezi
17. Sistem Sağlığı / Backup / Veri Migrasyonu
18. Hardening + performans + final audit

## Dikey dilim kuralı
Her modül şu sırayla tamamlanır:

`Migration → Model → Domain Service/Use Case → Transaction & invariant → Authorization → Controller/API → V16.3 UI → Feature/Integration Test → PostgreSQL CI`

Bir modülün UI'sı bitmiş sayılmaz; görünür her buton gerçek route/action çalıştırmalıdır. Placeholder veya dead button kabul edilmez.

## Ana modüller ve sahip oldukları veriler

### Core / Ayarlar
Firma, şube, kullanıcı, rol/yetki, belge numaralandırma, vergi/döviz, dönem, audit, entegrasyon ayarları.

### Cari
Cari kartı, iletişim/yetkililer, sevk/adres, cari iskonto, risk limiti, B2B erişimi ve `account_transactions` hareket defteri.

### Ürün / Stok
Ürün, barkod, depo, lokasyon, rezervasyon, transfer, sayım ve `stock_movements` stok otoritesi.

### Satış
Teklif, satış siparişi, sevkiyat/irsaliye, satış faturası ve satış iadesi.

### Alış
Satınalma siparişi, mal kabul, alış faturası ve alış iadesi.

### Kasa / Banka
Tahsilat, ödeme, gider, kasa/banka hareketleri, virman, ekstre içe aktarma ve mutabakat.

### Çek / Senet
Alınan/verilen çek ve senet; fiziksel konum, ön/arka görsel ve durum geçmişi.

### Üretim / Fason
Reçete, üretim emri, malzeme çıkışı, mamul girişi, fason gönderim/teslim/fire/eksik.

### İthalat
Sevkiyat/konteyner, ürün-koli/malzeme eşleme, maliyet dağıtımı, üretim uyumluluk listeleri ve yükleme simülasyonu.

### E-Ticaret / B2B
Tek Integration Core; WooCommerce, Trendyol ve dahili B2B adapterları.

### Raporlar
Hazır rapor merkezi; ilk sürümde generic report designer yok.

## Core olmayan / ilk sürümde yapılmayacaklar
- SaaS abonelik/tier sistemi
- Kubernetes / multi-region / hyperscale platform
- Ayrı search daemon
- Generic QMS/ECO/OEE/Shop Floor platformu
- Generic Visual Report Designer
- Open-banking/canlı banka API
- Core lot/seri sistemi
- Çoklu fiyat listeleri

Bu başlıklar gerçek ihtiyaç oluşmadan altyapıya eklenmez.
