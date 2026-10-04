# Üretim completion ve maliyet

## Completion transaction

K-143:

Aynı transaction içinde:

1. order + component snapshot lock,
2. completion remaining doğrulama,
3. actual consumption/fire doğrulama,
4. component stock out,
5. material cost hesaplama,
6. output location dağılımı doğrulama,
7. finished product stock in,
8. UpdateMovingAverage,
9. production_cost snapshot,
10. status/remaining update,
11. audit/idempotency.

## Actual consumption

K-136:

Reçete planned miktarı önerir fakat kullanıcı actual consumption'ı değiştirebilir.

Fark audit edilir.

## Fire

K-137:

Her component için:

- consumed_quantity
- fire_quantity

ayrı tutulur.

```
total_component_out = consumed + fire
```

İkisi de stoktan çıkar.

Fire maliyeti production cost'a dahildir.

## Source location

K-138:

Her component satırı kendi source location'ını taşır.

Negatif stok K-140 gereği ürün `allow_negative_stock` kuralına uyar.

## Output location

K-139:

Completion output miktarı birden fazla target location'a bölünebilir.

```
SUM(output quantities) = completed_quantity
```

Her output ayrı stock in üretir ancak aynı production unit cost kullanır.

## Production cost

K-144/K-145:

```
material_cost =
SUM((consumed + fire) × component stock-out unit_cost)

production_total_cost =
material_cost + subcontract_service_cost

production_unit_cost =
production_total_cost / completed_quantity
```

İç üretimde ilk sürüm subcontract_service_cost = 0; işçilik/enerji/overhead yoktur.

## Moving average

K-146:

Finished product stock-in:

- reason=production,
- unit_cost=production_unit_cost,
- moving average'ı günceller.

`product_costs.production_cost` son production unit cost snapshot'ıdır.

## Kısmi completion

K-141:

Aynı order birden çok completion alabilir.

Toplam completed quantity planned-cancelled sınırını aşamaz.

## Reverse

K-162:

Completion yerinde değiştirilmez.

Reverse yeni kayıtlarla:

- finished product stock out,
- component stock in,
- ilgili maliyet etkilerinin exact inverse zinciri

oluşturur.

Original immutable kalır.
