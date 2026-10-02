# MarsOtomasyon — Proje planı v2

## Çalışma yöntemi

Her faz önce veri modeli + iş kuralları + ekran + standalone G görevleri olarak yazılır. **Satır sayısı hedef değildir; uzunluk için dolgu yasaktır.** Yerel model görevleri tek tek uygular; gerçek PostgreSQL kabul testleri geçmeden ilerlenmez.

## Faz durumu

- Faz 0 Temel: G-001…G-021 yazıldı ve güncel mimariye göre temizlendi.
- Faz 0b UI: G-0b1…G-0b3 yazıldı ve temizlendi.
- Faz 1 Kartlar: G-101…G-115 period DB mimarisine göre güncellendi.
- Faz 2 Stok: G-201…G-212 yeni stok/rezervasyon kararlarına göre güncellendi.
- **Faz 3 Satış: DOKÜMANTASYON YAZILDI VE KALİTE KONTROLÜ TAMAMLANDI — veri modeli 30–34, iş kuralları 28–31, ekranlar ve G-300…G-312 hazırdır. Kodlama/uygulama tamamlanması kabul testlerine bağlıdır.**
- **Faz 4 Alış: DOKÜMANTASYON YAZILDI — K-086…K-091 kilitli; veri modeli 35, iş kuralları 32–34, alış ekranları ve G-400…G-409 hazırdır. Kodlama/uygulama tamamlanması G-401…G-409 gerçek PostgreSQL kabul testlerine bağlıdır.**
- **Faz 5 Kasa/Banka/Çek-Senet: DOKÜMANTASYON YAZILDI — K-092…K-097 kilitli; veri modeli 36–38, iş kuralları 35–38, finans ekranları ve G-500…G-509 hazırdır. Kodlama/uygulama tamamlanması G-501…G-509 gerçek PostgreSQL kabul testlerine bağlıdır.**
- **Faz 6 İade: DOKÜMANTASYON YAZILDI — K-098…K-113 kilitli; veri modeli 39, iş kuralları 39–41, iade ekranları ve G-600…G-609 hazırdır. Kodlama/uygulama tamamlanması G-601…G-609 gerçek PostgreSQL kabul testlerine bağlıdır. Faz 7 kullanıcı onayı olmadan başlamaz.**
- **Faz 7 İthalat: DOKÜMANTASYON YAZILDI — K-114…K-129 kilitli; veri modeli 40, iş kuralları 42–44, ithalat ekranları ve G-700…G-709 hazırdır. Yalnız A-053 (satış görmüş/sıfır-negatif stokta ek maliyetin moving average'a yansıma formülü) açıktır; bu kapanmadan Faz 7 dokümantasyonu tam kapalı sayılmaz.**
- Faz 8 Basit üretim/fason
- Faz 9 E-ticaret
- Faz 10 Raporlar/çıktılar/tasarımcı
- Faz 11b Dönem devri
- Faz 11 Canlı geçiş

## Faz 3 planı

G-300 özet; G-301 documents/document_lines; G-302 hesap; G-303 PostDocument; G-304 teklif; G-305 sipariş/rezervasyon; G-306 irsaliye; G-307 fatura; G-308 proforma; G-309 tahsilat/cari; G-310 araç sıcak satış; G-311 ters kayıt; G-312 test.

Faz 3 iş kuralı belgeleri mevcut numaralarla çakışmamak için 28–31 olacaktır.

## Kritik sıra

Repo temizliği ve Faz 0–2 görev revizyonu tamamlandı. Faz 3 Satış dokümantasyonu yazıldı ve kalite kontrolünden geçirildi. Faz 4 Alış dokümantasyonu K-086…K-091 kararlarıyla yazıldı; G-400…G-409 hazırdır. Faz 3 ve Faz 4'ün kodlama/uygulama tamamlanması kendi gerçek PostgreSQL kabul testlerine bağlıdır. Faz 5 Kasa/Banka/Çek-Senet dokümantasyonu K-092…K-097 kararlarıyla yazıldı; G-500…G-509 hazırdır. Faz 6 İade dokümantasyonu K-098…K-113 kararlarıyla yazıldı; G-600…G-609 hazırdır. Faz 5 ve Faz 6 kodlama/uygulama tamamlanması kendi gerçek PostgreSQL kabul testlerine bağlıdır. Faz 7 kullanıcı onayı olmadan başlatılmaz.


## Faz 4 planı

G-400 özet; G-401 belge/selection çekirdeği; G-402 satınalma talebi; G-403 tedarikçi teklif toplama; G-404 satınalma siparişi; G-405 mal kabul; G-406 alış faturası posting+maliyet; G-407 kısmi faturalama; G-408 ters kayıt/bütünlük; G-409 test.

Faz 4 yeni veri modeli dosyası 35; yeni iş kuralı dosyaları 32–34'tür. Ortak Faz 3 belge çekirdeği yeniden kullanılmaktadır.


## Faz 5 planı

G-500 özet; G-501 finans şema genişletmesi; G-502 virman; G-503 tedarikçi ödeme; G-504 kasa sayımı; G-505 manuel banka mutabakatı; G-506 çek/senet şeması; G-507 çek/senet yaşam döngüsü; G-508 risk/ters kayıt/integrity; G-509 test.

Faz 5 yeni veri modeli dosyaları 36–38; yeni iş kuralı dosyaları 35–38'dir. Faz 3 minimum cash/bank çekirdeği yeniden kullanılmaktadır.


## Faz 6 planı

G-600 özet; G-601 iade şema genişletmesi; G-602 kaynak çözümleme; G-603 satış iadesi; G-604 alış iadesi; G-605 kısmi/çoklu iade; G-606 karantina kontrolü; G-607 cross-period iade; G-608 reverse/integrity; G-609 test.

Faz 6 yeni veri modeli dosyası 39; yeni iş kuralı dosyaları 39–41'dir. Ortak belge, stok, cari ve finans çekirdeği yeniden kullanılmaktadır.


## Faz 7 planı

G-700 özet; G-701 ithalat şeması; G-702 yaşam döngüsü; G-703 purchase invoice line kaynakları; G-704 import expense; G-705 masraf dağıtımı; G-706 finalize/import cost; G-707 inventory cost adjustment; G-708 late cost/reverse/integrity; G-709 test.

Faz 7 yeni veri modeli dosyası 40; yeni iş kuralı dosyaları 42–44'tür. Faz 4 purchase invoice ve moving-average çekirdeği yeniden kullanılmaktadır. A-053 kapanınca G-707/G-709'un son maliyet formülü kesinleşir.
