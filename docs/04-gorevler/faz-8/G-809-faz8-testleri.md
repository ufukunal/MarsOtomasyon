# G-809 — Faz 8 bütünleşik testler

## Amaç

Reçete, production order, completion, fire, maliyet, multi-location output ve fason akışını gerçek PostgreSQL üzerinde bütünleşik doğrulamak.

## Önkoşul

G-801…G-808.

## Dokunulacak dosyalar

- Faz8ProductionFlowTest
- Faz8SubcontractFlowTest
- Faz8ProductionConcurrencyTest
- Faz8ProductionIntegrityTest

## Şema / Kod

Yeni production şeması yok.

## Test kapsamı

- recipe Rev.N immutable
- single active recipe
- output_quantity scaling
- production order recipe snapshot
- production-mode sales order draft order
- partial completion / remaining cancel
- actual consumption deviation audit
- component-level fire
- per-component source location
- multi-target output location
- allow_negative_stock behavior
- component material cost
- production moving average
- product_costs.production_cost
- subcontract location transfer
- sales exclusion of subcontract stock
- partial subcontract send/return
- service purchase_invoice relation
- completion before service invoice
- late subcontract cost adjustment
- reverse
- rollover recipes/subcontract stock
- integrity:production
- integrity:recipes

## Kurallar

- Gerçek PostgreSQL; SQLite yok.
- Beklenen maliyetler production calculator'dan türetilmez.
- K-131…K-162 ile çelişki yok.
- Faz 9 e-commerce davranışı eklenmez.

## Kabul ölçütü

- Tüm suite yeşil.
- Pint yeşil.
- Larastan level 6 yeni hata yok.
- Stock/maliyet çift etkisi yok.
- Multi-location totals tutarlı.
- Yeni ürün kararı kodda uydurulmamış.

## İstem

> Faz 8 basit üretim/fason için gerçek PostgreSQL integration/concurrency/integrity testlerini yaz.
