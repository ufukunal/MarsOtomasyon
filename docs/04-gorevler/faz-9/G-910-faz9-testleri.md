# G-910 — Faz 9 bütünleşik testler

## Amaç

Kanal hesapları, listing, içerik/görsel, stok/fiyat, order/cancel/return, webhook/polling, retry ve rollover davranışını gerçek PostgreSQL üzerinde doğrulamak.

## Önkoşul

G-901…G-909.

## Dokunulacak dosyalar

- Faz9ChannelFlowTest
- Faz9ChannelStockPriceTest
- Faz9ChannelOrderTest
- Faz9ChannelConcurrencyTest
- Faz9ChannelIntegrityTest

## Şema / Kod

Yeni production şeması yok.

## Test kapsamı

- multi-account same platform
- credential encryption
- listing link/publish
- per-variant listing
- content/image fallback
- manual category metadata
- selected-location stock aggregation
- subcontractor exclusion
- max/withhold
- production/manual stock mode
- set stock
- TRY price override/fallback
- confirmed order import
- cross-period external order idempotency
- marketplace customer + buyer/shipping snapshot
- no collection on import
- external price/discount snapshot
- partial cancel
- draft return
- shipment outbound
- webhook/polling
- 30/60/120 retry
- sync error/manual retry
- safe sync history
- rollover mapping
- K-256 carried open channel sales_order snapshot/provenance korunması, sync history taşınmaması
- integrity:channels/channel-orders

## Kurallar

- Gerçek PostgreSQL.
- Master + en az iki period DB ile cross-period test.
- External API gerçek network yerine adapter fake kullanır.
- K-163…K-201 ile çelişki yok.
- Faz 10 rapor/çıktı kapsamı eklenmez.

## Kabul ölçütü

- Suite yeşil.
- Pint yeşil.
- Larastan level 6 yeni hata yok.
- Duplicate external order yok.
- Location scope dışı stok kanala gitmiyor.
- Hassas credential/payload sızıntısı yok.
- Yeni ürün kararı uydurulmamış.

## İstem

> Faz 9 E-ticaret için gerçek PostgreSQL integration/concurrency/integrity testlerini adapter fake'leriyle yaz.
