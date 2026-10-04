# İthalat maliyet finalize ve adjustment

## Finalize öncesi satır maliyeti

Kaynak purchase_invoice satırı zaten Faz 4'te:

- stock in,
- purchase unit cost,
- moving average update

üretmiştir.

Faz 7 fiziksel stok miktarını yeniden yazmaz.

## Final import unit cost

Bir import file line için:

```
allocated_import_cost =
SUM(inventory-cost expense allocations)

final_import_total_cost =
purchase_value_base + allocated_import_cost

final_import_unit_cost =
final_import_total_cost / base_quantity
```

K-124 gereği ürünün son finalized import unit cost snapshot'ı `product_costs.import_cost` alanına yazılır.

## Cost adjustment amount

Purchase invoice maliyeti zaten moving average'a girmiştir.

Bu yüzden finalize adjustment yalnız farktır:

```
adjustment_amount =
allocated_import_cost
```

Fiziksel quantity değişmez.

## Moving average adjustment

K-123 gerçek kaynak: `inventory_cost_adjustments`.
K-130 maliyet formülünü kilitler.

Her import line/product için:

```
unit_adjustment = allocated_import_cost / original_import_base_quantity
moving_average_after = moving_average_before + unit_adjustment
```

Kurallar:

1. `product_costs` row `lockForUpdate` alınır.
2. Fiziksel stock quantity değiştirilmez.
3. Ek maliyet `amount_base` ayrı `inventory_cost_adjustments` kaydına yazılır.
4. `quantity_basis` kaynak ithalat satırının original `base_quantity` değeridir; current on-hand değildir.
5. Current stock quantity sıfır veya negatif olsa bile formül uygulanabilir.
6. Geçmiş sales stock movement unit_cost kayıtları geriye dönük değiştirilmez.
7. `stock_movements` yazılmaz.

## Sonradan gelen masraf

K-122:

- finalized dosya açılmaz,
- yeni import cost adjustment kaydı açılır,
- masraf aynı import line/product setine dağıtılır,
- yeni inventory_cost_adjustments oluşturulur,
- import_file adjusted görünür.

## Reverse

Finalized cost adjustment mutate edilmez.

Reverse:

- exact inverse amount_base,
- yeni inventory_cost_adjustments,
- moving_average tekrar hesaplanır,
- original adjustment_of/reversal bağı korunur.

## Concurrency

- product_costs row lockForUpdate,
- import_file version,
- idempotency,
- deterministic product lock order.

Aynı import dosyası ikinci kez finalize edilemez.

## Integrity

`integrity:cost-adjustments`:

- adjustment total = inventory-cost allocation total,
- physical stock movement oluşmaması,
- before/after moving average hesabı,
- duplicate finalize olmaması,
- import_cost snapshot son finalized import maliyetiyle eşleşmesi

kontrollerini yapar.
