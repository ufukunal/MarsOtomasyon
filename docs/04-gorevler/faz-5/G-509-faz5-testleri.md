# G-509 — Faz 5 bütünleşik testler

## Amaç

Kasa/banka, virman, tedarikçi ödeme, kasa sayımı, manuel banka mutabakatı ve çek/senet yaşam döngüsünü gerçek PostgreSQL üzerinde bütünleşik doğrulamak.

## Önkoşul

G-501…G-508 tamamlanmış olmalı.

## Dokunulacak dosyalar

- `tests/Feature/Finance/Faz5FinanceFlowTest.php`
- `tests/Feature/Finance/Faz5SecuritiesFlowTest.php`
- `tests/Feature/Finance/Faz5ConcurrencyTest.php`
- `tests/Feature/Finance/Faz5IntegrityTest.php`

## Şema / Kod

Yeni production şeması yok. Faz 5 sözleşmelerini ve integrity komutlarını test eder.

## Test kapsamı

### Virman

- cash→cash
- cash→bank
- bank→cash
- bank→bank
- same account reject
- different currency reject
- source out + target in same amount
- partial transaction rollback
- idempotency
- reverse

### Tedarikçi ödeme

- general payment
- purchase invoice shortcut
- source invoice optional
- partial amount
- supplier debit
- cash/bank out
- no settlement table/paid column
- reverse

### Kasa sayımı

- zero difference
- positive difference
- negative difference
- reason required on difference
- confirm-time system balance refresh
- optimistic lock
- audit

### Banka mutabakatı

- null/true/false states
- financial amount immutable
- permission
- optimistic lock
- audit
- no statement import/parser

### Çek/senet

- received initial credit
- issued initial debit
- portfolio transition
- endorse target debit / no second original effect
- send_to_collection
- collected bank in / no second cari
- paid bank out / no second cari
- bounce/return exact inverse
- bank operation metadata
- illegal transition
- idempotency/concurrency

### Risk

- portfolio included
- sent_to_collection included
- endorsed excluded
- collected excluded
- bounced excluded
- issued excluded
- no double count

### Integrity

- integrity:finance
- integrity:securities
- integrity:contacts
- period isolation

## Kurallar

- Gerçek PostgreSQL; SQLite yok.
- Beklenen tutarlar production fonksiyondan türetilmez.
- İki fiziksel period DB ile izolasyon test edilir.
- Faz 5'te ekstre importu, otomatik matching veya FX dönüşüm motoru eklenmez.

## Kabul ölçütü

- Tüm Faz 5 suite yeşil.
- Pint yeşil.
- Larastan level 6 yeni hata yok.
- K-092…K-097 ile çelişen davranış yok.
- Açık olmayan yeni ürün kararı kodda uydurulmamış.

## İstem

> Faz 5 Kasa/Banka/Çek-Senet için gerçek PostgreSQL integration/concurrency/integrity testlerini yaz. Finansal çift etkileri ve security event geçmişini bağımsız elle beklenen değerlerle doğrula.
