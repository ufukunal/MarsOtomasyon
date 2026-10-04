# G-507 — Çek/Senet yaşam döngüsü ve cari etkiler

## Amaç

K-082/K-096/K-097 event geçişlerini ve cari/finans etkilerini tek transaction zincirinde uygulamak.

## Önkoşul

G-506, G-303, G-503 finance/contact posting altyapısı.

## Dokunulacak dosyalar

- çek/senet create/detail bileşenleri
- security transition Action'ları
- cari/finance movement helper entegrasyonu
- security lifecycle feature/concurrency testleri

## Şema / Kod

Received:

- first receive => original contact credit
- portfolio
- endorse => target contact debit, original ikinci kez yok
- send_to_collection => bank context, cari yok
- collect => bank in, cari yok
- bounce/return => ilgili önceki cari etkilerini exact inverse

Issued:

- first issue => original contact debit
- awaiting_payment
- pay => bank out, cari yok
- return/cancel => original debit inverse credit

Her transition security_event üretir.

## Kurallar

- Security row lockForUpdate + version.
- Transition listesi direction/status'a göre merkezî resolver'da.
- Aynı transition idempotent.
- Cari etkili event contact_transaction_id taşır.
- Collected/paid ikinci cari hareket üretmez.
- Original history silinmez.
- Faz 5 yeni FX değerleme/kur farkı motoru oluşturmaz.

## Kabul ölçütü

- Received ilk kayıt customer credit oluşturuyor.
- Issued ilk kayıt counterparty debit oluşturuyor.
- Endorse original cariyi ikinci kez etkilemiyor ve target debit oluşturuyor.
- Send_to_collection cari üretmiyor.
- Collect bank in, cari yok.
- Pay bank out, cari yok.
- Bounce/return exact inverse cari hareket üretiyor.
- Illegal transition reddediliyor.
- Concurrent iki transition'dan yalnız geçerli biri commit ediyor.
- Duplicate idempotency event/hareket çoğaltmıyor.

## İstem

> K-082/K-096 lifecycle'ı security_events üzerinden uygula. Cari etkiyi event semantiğinden ikinci kez türetip çoğaltma; event ile üretilen hareket arasında açık referans tut.
