# G-906 — Kanal sipariş importu

## Amaç

External order'ı idempotent confirmed sales_order'a dönüştürmek ve buyer/shipping snapshot'ını korumak.

## Önkoşul

G-901, Faz 3 sales_order, Master event registry.

## Dokunulacak dosyalar

- channel_external_event_registry migration/model
- channel_account_period_settings migration/model
- channel_order_snapshots migration/model
- ImportChannelOrder Action
- marketplace customer resolver
- order import tests

## Şema / Kod

Unique:

```
channel_account_id + event_type=order + external_order_id
```

Imported sales_order:

- confirmed
- channel account marketplace customer contact
- external line price/discount frozen
- buyer/shipping snapshot

## Kurallar

- Contact gerçek buyer için çoğaltılmaz.
- Channel account için period-level marketplace customer mapping `channel_account_period_settings` üzerinden çözülür.
- Collection/cash-bank yok.
- Payout/commission yok.
- Production-mode ürün Faz 8 draft production order entegrasyonunu tetikleyebilir.
- Aynı event ikinci sales_order oluşturmaz.

## Kabul ölçütü

- External order confirmed sales_order oluyor.
- Duplicate pull ikinci order oluşturmuyor.
- Buyer/shipping snapshot doğru.
- External fiyat/discount korunuyor.
- Collection oluşmuyor.
- Production-mode item draft production order açabiliyor.

## İstem

> K-179…K-187 kanal order importunu mevcut sales_order çekirdeğine idempotent bağla.
