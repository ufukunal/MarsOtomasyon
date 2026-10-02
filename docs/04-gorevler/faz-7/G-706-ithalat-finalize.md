# G-706 — İthalat finalize ve import cost

## Amaç

Dağıtılmış ithalat ek maliyetlerinden final import unit cost hesaplamak ve finalize transaction'ını hazırlamak.

## Önkoşul

G-701…G-705.

## Dokunulacak dosyalar

- FinalizeImportFile Action
- import cost calculator
- product_costs.import_cost update
- finalize tests

## Şema / Kod

Satır:

```
final_import_total =
purchase_value_base + allocated_inventory_cost

final_import_unit =
final_import_total / base_quantity
```

K-124:

- product_costs.import_cost = son finalized import unit cost snapshot.

## Kurallar

- Finalize öncesi tüm inventory-cost expense tam dağıtılmış olmalı.
- Stock quantity ikinci kez değişmez.
- Import file cari hareket üretmez.
- Finalized immutable.
- Aynı file ikinci kez finalize edilmez.
- Moving average etkisi G-707 inventory cost adjustment üzerinden.

## Kabul ölçütü

- Final import unit cost doğru.
- import_cost snapshot doğru.
- Stock movement oluşmuyor.
- Contact transaction oluşmuyor.
- Eksik allocation finalize'ı blokluyor.
- Idempotency duplicate finalize üretmiyor.

## İstem

> K-114/K-124/K-125 finalize hesaplarını fiziksel stok hareketi üretmeden uygula.
