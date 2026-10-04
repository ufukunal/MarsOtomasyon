# Ekran — Çok Dönemli Rapor

## Amaç
Yetkili kullanıcının bir veya daha fazla şirket/yıl period DB'sini tek raporda karşılaştırması/konsolide etmesi.

## Seçim
- şirket
- yıl/dönem
- tarih aralığı
- rapor
- gruplama

## Kurallar
- reports.consolidated zorunlu.
- Her period erişimi ayrıca doğrulanır.
- DB'ler ayrı sorgulanır.
- Her satır period metadata taşır.
- Archived/detached period açık hata verir.
- Aynı şirket trend karşılaştırması ve toplam görünümü desteklenir.
- Cross-company birleştirme yalnız raporun desteklediği ortak business key ile.
