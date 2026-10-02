# G-707 — Inventory cost adjustment ve moving average

## Amaç

İthalat ek maliyetini K-123 gereği ayrı maliyet adjustment kaydıyla moving average'a yansıtmak.

## Önkoşul

G-706.

## Dokunulacak dosyalar

- ApplyInventoryCostAdjustment
- inventory_cost_adjustments repository/model
- product cost lock helper
- cost adjustment tests

## Şema / Kod

Adjustment amount:

```
allocated_import_cost
```

Fiziksel stock movement yoktur.

Snapshot:

- quantity_basis
- amount_base
- unit_adjustment_base
- moving_average_before
- moving_average_after

## Kurallar

- product_costs lockForUpdate.
- deterministic product lock order.
- Money/BCMath.
- stock_movements yazılmaz.
- adjustment gerçek kaynaktır; product_costs yalnız snapshot'tır.
- A-053: current stock quantity <= 0 olduğunda davranış kullanıcı kararı bekliyor; kod uydurulmaz.

## Kabul ölçütü

- Positive stock quantity senaryosunda moving average adjustment doğru.
- Stock quantity değişmiyor.
- inventory_cost_adjustment audit/history mevcut.
- Concurrent products deterministik lock kullanıyor.
- A-053 çözülmeden <=0 quantity davranışı uygulanmıyor.

## İstem

> K-123 inventory cost adjustment mekanizmasını ayrı gerçek kaynak olarak uygula. A-053 çözülmeden sıfır/negatif stok için davranış uydurma.
