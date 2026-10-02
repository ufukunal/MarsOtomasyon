# Kanal webhook, polling, sync history ve bütünlük

## Inbound mekanizma

K-194:

- webhook varsa birincil,
- polling güvenlik ağı.

K-195:

- order/cancel/return polling: 15 dakika,
- manuel tetikleme destekli.

## Event idempotency

K-197:

Master channel_external_event_registry:

- kanal hesabı,
- event type,
- external id

unique kombinasyonunu tutar.

Event hedef açık period'a yönlendirilir.

Aynı external event eski/yeni period sorgularında tekrar görülse de ikinci business record oluşmaz.

## Sync history

K-201:

Her dış API operasyonu için channel_sync_events:

- direction
- entity
- action
- external id
- status
- attempt
- correlation id
- payload hash
- error summary
- timestamps

tutar.

Hassas tam payload saklanmaz.

## Retry

K-193:

1. ilk hata → 30 sn
2. ikinci → 60 sn
3. üçüncü → 120 sn
4. başarısız → channel_sync_errors

Manuel retry yeni audit event üretir.

## Dönem devri

K-196:

- account Master'da kalır,
- listing mapping/location kapsamı yeni period'a taşınır,
- external listing ids korunur,
- order/sync history taşınmaz.

## Integrity

`integrity:channels`:

- active listing product/account mapping,
- location kapsamı,
- subcontractor location yasağı,
- mode alanlarının tutarlılığı,
- duplicate listing mapping

kontrollerini yapar.

`integrity:channel-orders`:

- period channel order snapshot ↔ sales_order,
- Master event registry target,
- duplicate external order,
- cancel/return source provenance

kontrollerini yapar.

Otomatik düzeltme yoktur.
