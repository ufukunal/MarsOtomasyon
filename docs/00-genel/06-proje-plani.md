# MarsOtomasyon — Proje planı v2

## Çalışma yöntemi

Her faz önce veri modeli + iş kuralları + ekran + standalone G görevleri olarak yazılır. **Satır sayısı hedef değildir; uzunluk için dolgu yasaktır.** Yerel model görevleri tek tek uygular; gerçek PostgreSQL kabul testleri geçmeden ilerlenmez.

## Faz durumu

- Faz 0 Temel: G-001…G-021 yazıldı ve güncel mimariye göre temizlendi.
- Faz 0b UI: G-0b1…G-0b3 yazıldı ve temizlendi.
- Faz 1 Kartlar: G-101…G-115 period DB mimarisine göre güncellendi.
- Faz 2 Stok: G-201…G-212 yeni stok/rezervasyon kararlarına göre güncellendi.
- **Faz 3 Satış: YAZILDI — veri modeli 30–34, iş kuralları 28–31, ekranlar ve G-300…G-312 hazır; kullanıcı/Claude kontrolü bekleniyor. Faz 4 başlamaz.**
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

Repo temizliği ve Faz 0–2 görev revizyonu tamamlandı. Şu an yalnız Faz 3 Satış dokümantasyonu hazırlanır; Faz 4 kullanıcı onayı olmadan başlatılmaz.
