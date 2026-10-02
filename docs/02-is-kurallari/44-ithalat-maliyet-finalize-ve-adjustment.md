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

Kesin olanlar:

1. product_costs row lockForUpdate alınır,
2. fiziksel stock quantity değiştirilmez,
3. ek maliyet amount_base ayrı inventory_cost_adjustment kaydına yazılır,
4. before/after snapshot saklanır,
5. stock_movements yazılmaz.

**[KARAR GEREKİYOR — A-053]**

Finalize anında kaynak ithalat ürününün bir kısmı veya tamamı satılmış olabilir. Lot/parti takibi olmadığı için mevcut stok içindeki hangi birimlerin bu ithalata ait olduğu bilinmez. Bu nedenle tüm adjustment tutarını current on-hand quantity üzerine bölmek kanonik karar olmadan uygulanmaz.

A-053 kapandığında moving_average_after formülü ve current quantity <= 0 davranışı bu bölüme işlenecektir.

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
