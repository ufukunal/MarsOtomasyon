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
- K-130: `unit_adjustment = amount_base / original_import_base_quantity`.
- `moving_average_after = moving_average_before + unit_adjustment`.
- Current on-hand quantity formülün paydası değildir.
- Current quantity <= 0 olsa da snapshot adjustment uygulanabilir.
- Geçmiş stock movement maliyetleri geriye dönük değiştirilmez.

## Kabul ölçütü

- unit_adjustment original import base_quantity üzerinden doğru.
- Positive, zero ve negative current stock senaryolarında aynı K-130 formülü uygulanıyor.
- Stock quantity değişmiyor.
- Geçmiş sales stock movement unit_cost değişmiyor.
- inventory_cost_adjustment audit/history mevcut.
- Concurrent products deterministik lock kullanıyor.

## İstem

> K-123/K-130 inventory cost adjustment mekanizmasını ayrı gerçek kaynak olarak uygula; birim adjustment'ı original import base_quantity üzerinden hesapla ve fiziksel stok miktarını değiştirme.
