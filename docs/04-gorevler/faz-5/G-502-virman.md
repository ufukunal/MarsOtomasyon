# G-502 — Kasa/Banka virman

## Amaç

K-092'ye göre kasa↔kasa, kasa↔banka ve banka↔banka aynı para birimli virmanları tek transaction içinde uygulamak.

## Önkoşul

G-501, G-303 posting/idempotency altyapısı.

## Dokunulacak dosyalar

- FinanceTransfer form/detail bileşenleri
- `PostFinanceTransfer` Action
- finance transfer posting profile/helper
- virman feature/concurrency testleri

## Şema / Kod

Input:

- document_date
- source_account_type/id
- target_account_type/id
- amount
- note
- idempotency_key

Posting:

- source movement = out
- target movement = in
- contact transaction yok

## Kurallar

- Dört hesap türü kombinasyonu desteklenir.
- Source = target olamaz.
- Source.currency = target.currency.
- FX conversion/rate difference yok.
- İki finans hareketi tek transaction'dır.
- Hesaplar deterministik sırayla lockForUpdate alınır.
- Posted transfer mutate edilmez.

## Kabul ölçütü

- Cash→Cash, Cash→Bank, Bank→Cash, Bank→Bank çalışıyor.
- Farklı currency transfer reddediliyor.
- Aynı hesap source+target reddediliyor.
- Tek taraflı hareket commit edilemiyor.
- Idempotency duplicate transfer üretmiyor.
- Concurrent transfer hesap hareketlerini çiftlemiyor.
- Reverse source/target etkilerini doğru tersliyor.

## İstem

> K-092 ve iş kuralı 35'e göre finance_transfer uygula. Transferi iki bağımsız işlem gibi yazma; tek transaction ve tek idempotency zinciri kullan.
