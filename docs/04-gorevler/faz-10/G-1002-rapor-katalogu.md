# G-1002 — Operasyonel rapor kataloğu

## Amaç
Satış, alış, cari, stok, finans, çek/senet, iade, ithalat, üretim/fason ve e-ticaret raporlarını ortak motorla sağlamak.

## Önkoşul
G-1001.

## Dokunulacak dosyalar
- domain report definitions/queries
- report center UI
- report-specific tests

## Şema / Kod
Kanonik katalog: iş kuralı 53.

## Kurallar
- Kaynak domain tabloları kanoniktir.
- Kâr/maliyet cost.view ile korunur.
- Resmi muhasebe/vergi raporu yok.
- Rapor toplamları mevcut frozen/ledger değerlerinden gelir.

## Kabul ölçütü
- Her katalog grubunda en az tanımlı raporlar registry'de.
- Stok/cari sonuçları gerçek kaynaklarla eşleşiyor.
- İthalat/üretim/e-ticaret fazları raporlanıyor.
- Genel muhasebe çıktısı üretilmiyor.

## İstem
> İş kuralı 53 rapor kataloğunu ortak report engine üzerinde uygula.
