# G-709 — Faz 7 bütünleşik testler

## Amaç

İthalat dosyası, farklı dövizler, expense kaynakları, allocation, finalize, import cost ve inventory cost adjustment davranışını gerçek PostgreSQL üzerinde doğrulamak.

## Önkoşul

G-701…G-708 tamamlanmış olmalı.

## Dokunulacak dosyalar

- Faz7ImportFlowTest
- Faz7ImportAllocationTest
- Faz7ImportConcurrencyTest
- Faz7ImportIntegrityTest

## Şema / Kod

Yeni production şeması yok.

## Test kapsamı

- multi purchase invoice
- multi supplier
- unique invoice line membership
- no quantity split
- purchase invoice/manual expense
- USD/EUR/TRY frozen exchange rates
- import VAT excluded
- purchase value allocation
- quantity allocation
- manual allocation
- deterministic rounding
- finalize immutable/idempotent
- import_cost snapshot
- no stock quantity change on cost adjustment
- no import-file contact movement
- late cost adjusted state
- reverse exact inverse
- product cost concurrency
- integrity:imports
- integrity:cost-adjustments
- K-130 original-import-quantity cost adjustment behavior

## Kurallar

- Gerçek PostgreSQL; SQLite yok.
- Beklenen tutarlar production calculator'dan türetilmez.
- K-114…K-130 ile çelişki yok.
- Faz 8 production veya Faz 9 e-commerce davranışı eklenmez.

## Kabul ölçütü

- Suite yeşil.
- Pint yeşil.
- Larastan level 6 yeni hata yok.
- Stock quantity cost adjustment'ta değişmiyor.
- Allocation totals tam.
- Yeni ürün kararı kodda uydurulmamış.

## İstem

> Faz 7 İthalat için gerçek PostgreSQL integration/concurrency/integrity testlerini yaz; fiziksel stok ve maliyet adjustment ayrımını bağımsız doğrula.
