# MarsOtomasyon — Proje planı v2

## Çalışma yöntemi

Her faz önce veri modeli + iş kuralları + ekran + 300–500 satır standalone G görevleri olarak yazılır. Yerel model görevleri tek tek uygular; gerçek PostgreSQL kabul testleri geçmeden ilerlenmez.

## Faz durumu

- Faz 0 Temel: G-001…G-021 mevcut; güncel mimariye göre detaylandırma/revizyon yapılacak.
- Faz 0b UI: G-0b1…G-0b3 mevcut; revizyon yapılacak.
- Faz 1 Kartlar: G-101…G-115 mevcut; kartların period DB'de olduğu kurala göre revizyon yapılacak.
- Faz 2 Stok: G-201…G-212 mevcut; yeni satış/rezervasyon kararlarıyla revizyon yapılacak.
- **Faz 3 Satış: sıradaki yeni faz; repo temizliği bitmeden başlatılmaz.**
- Faz 4 Alış
- Faz 5 Kasa/Banka (Faz 3 minimum cash/bank altyapısını erkenden kurar)
- Faz 6 İade
- Faz 7 İthalat
- Faz 8 Basit üretim/fason
- Faz 9 E-ticaret
- Faz 10 Raporlar/çıktılar/tasarımcı
- Faz 11b Dönem devri
- Faz 11 Canlı geçiş

## Faz 3 planı

G-300 özet; G-301 documents/document_lines; G-302 hesap; G-303 PostDocument; G-304 teklif; G-305 sipariş/rezervasyon; G-306 irsaliye; G-307 fatura; G-308 proforma; G-309 tahsilat/cari; G-310 araç sıcak satış; G-311 ters kayıt; G-312 test.

Faz 3 iş kuralı belgeleri mevcut numaralarla çakışmamak için 28–31 olacaktır.

## Kritik sıra

Önce mevcut repo legacy company_id/global scope/company DB/master kart kalıntılarından temizlenir. Sonra Faz 0–2 görevleri aynı G numaraları korunarak standalone standarda yükseltilir. Ardından Faz 3 yazılır.
