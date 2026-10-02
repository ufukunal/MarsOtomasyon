# G-805 — Production cost ve moving average

## Amaç

Actual component consumption/fire maliyetlerinden mamul production unit cost hesaplamak ve finished product moving average'a sokmak.

## Önkoşul

G-804, UpdateMovingAverage.

## Dokunulacak dosyalar

- ProductionCostCalculator
- production completion posting profile
- product_costs production_cost update
- production cost tests

## Şema / Kod

```
material_cost =
SUM((consumed + fire) × component stock-out unit_cost)

production_total_cost =
material_cost + subcontract_service_cost

production_unit_cost =
production_total_cost / completion_quantity
```

Internal production ilk sürümde service/overhead = 0.

## Kurallar

- Actual movement unit_cost snapshot kullanılır.
- Fire maliyete dahil.
- Finished product tüm output location'larda aynı unit cost ile girer.
- production stock-in moving average günceller.
- product_costs.production_cost son production unit cost snapshot.
- Float yok.

## Kabul ölçütü

- Elle beklenen material cost eşleşiyor.
- Fire dahil.
- Multi-location output maliyeti aynı.
- Moving average doğru.
- Internal overhead eklenmiyor.
- product_costs.production_cost doğru.

## İstem

> K-144…K-146 production cost hesabını actual component movement maliyetleriyle uygula.
