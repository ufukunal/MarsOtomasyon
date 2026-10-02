# Karantina kontrolü

## Temel

K-015/K-102.

Satış iadesi fiziksel stok girişini yapar fakat aynı miktarı quarantine olarak ayırır.

Karantina miktarı fiziksel quantity'nin içindedir.

## Kısmi karar

Bir entry için:

```
pending = quantity - released - scrapped
```

Kullanıcı pending miktarın bir kısmını release, bir kısmını scrap yapabilir.

## Release

- quarantine azalır,
- physical quantity değişmez,
- stock movement yok,
- moving average değişmez.

## Scrap

- quarantine azalır,
- `RecordStockMovement(out, reason=scrap)`,
- physical quantity azalır.

## Yasaklar

Pending quarantine:

- satılamaz,
- rezerve edilemez,
- transfer edilemez.

## Concurrency

Entry + stock_balance aynı transaction'da lock edilir. Aynı pending miktar iki kez release/scrap edilemez.

## Dönem devri

Açık pending miktar yeni döneme taşınır. Taşınan record source provenance/snapshot'ı korur.

## Integrity

`integrity:quarantine`:

- pending formülü,
- stock_balances.quarantine toplamı,
- release'de stock movement olmaması,
- scrap'ta doğru out movement,
- aşırı karar verilmemesi

kontrollerini yapar.
