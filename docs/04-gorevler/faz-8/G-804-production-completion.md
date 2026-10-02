# G-804 — Production completion

## Amaç

Actual consumption, component fire ve çoklu output location ile component out + finished product in işlemini atomik uygulamak.

## Önkoşul

G-803, RecordStockMovement.

## Dokunulacak dosyalar

- ProductionCompletion form
- PostProductionCompletion
- production consumption/output helpers
- completion/concurrency tests

## Şema / Kod

Input:

- completion quantity
- component actual consumption
- component fire
- component source location
- output location + quantity rows

## Kurallar

- Reçete planned miktarı öneridir.
- Actual değişebilir, fark audit edilir.
- consumed + fire stoktan çıkar.
- allow_negative_stock mevcut product kuralına uyar.
- output birden çok target location'a bölünebilir.
- output toplamı completion quantity.
- tek transaction/idempotency.

## Kabul ölçütü

- Component out temel birimde.
- Fire ayrı quantity ama aynı stock out maliyet zincirinde.
- Multi-location output toplamı doğru.
- Bir target output hata verirse tüm completion rollback.
- Concurrent completion remaining aşmıyor.
- Duplicate idempotency çift hareket üretmiyor.

## İstem

> K-136…K-143 production completion'ı çoklu output location ile tek transaction içinde uygula.
