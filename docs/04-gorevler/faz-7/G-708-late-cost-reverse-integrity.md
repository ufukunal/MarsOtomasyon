# G-708 — Late cost, reverse ve integrity

## Amaç

Finalized import file sonrası gelen masrafları immutable adjustment akışıyla işlemek ve Faz 7 bütünlük kontrollerini tamamlamak.

## Önkoşul

G-706, G-707.

## Dokunulacak dosyalar

- ImportCostAdjustment flow
- reverse adjustment Action
- IntegrityImports
- IntegrityCostAdjustments
- feature/integrity tests

## Şema / Kod

K-122:

- finalized file reopen yok,
- late expense ayrı adjustment,
- aynı line/product setine dağıtım,
- yeni inventory_cost_adjustments,
- import file görünür durumu adjusted.

Reverse:

- exact inverse adjustment,
- original mutate edilmez.

## Kurallar

- Physical stock quantity değişmez.
- Cari hareket yok.
- Duplicate reverse yok.
- Allocation history immutable.
- Integrity otomatik düzeltme yapmaz.
- A-053 edge-case'i bu görevde de uydurulmaz.

## Kabul ölçütü

- Late cost eski dosyayı değiştirmiyor.
- Adjusted status doğru.
- Reverse exact inverse adjustment oluşturuyor.
- integrity:imports membership/allocation farklarını buluyor.
- integrity:cost-adjustments moving average snapshot farkını buluyor.
- Stock quantity değişmiyor.

## İstem

> K-121/K-122 late-cost ve reverse zincirini immutable maliyet kayıtlarıyla tamamla.
