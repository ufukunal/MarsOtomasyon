# G-609 — Faz 6 bütünleşik testler

## Amaç

Satış/alış iadesi, kaynak çözümü, maliyet, partial, quarantine, cross-period ve reverse davranışını gerçek PostgreSQL üzerinde doğrulamak.

## Önkoşul

G-601…G-608.

## Dokunulacak dosyalar

- Faz6ReturnFlowTest
- Faz6ReturnConcurrencyTest
- Faz6CrossPeriodTest
- Faz6IntegrityTest

## Şema / Kod

Yeni production şeması yok.

## Test kapsamı

- sales_return source/manual/prior-period
- purchase_return source/manual/prior-period
- frozen price/discount/VAT/unit/conversion
- original frozen FX
- sales original unit_cost
- manual sales current moving average
- purchase cost basis two choices
- partial 60+40 and overflow
- reverse capacity reopen
- stock + quarantine semantics
- partial release/scrap
- no automatic cash/bank
- reason code validation
- manual source permission/audit
- concurrent returns
- concurrent quarantine decision
- two physical period DB cross-period
- integrity:returns/quarantine/stock/contacts

## Kurallar

- Gerçek PostgreSQL, SQLite yok.
- Beklenen tutarlar production fonksiyonundan türetilmez.
- Faz 7 ithalat veya Faz 9 marketplace return behavior eklenmez.

## Kabul ölçütü

- Suite yeşil.
- Pint yeşil.
- Larastan level 6 yeni hata yok.
- K-098…K-113 ile çelişki yok.
- Yeni ürün kararı uydurulmamış.

## İstem

> Faz 6 iade için gerçek PostgreSQL integration/concurrency/cross-period/integrity testlerini yaz.
