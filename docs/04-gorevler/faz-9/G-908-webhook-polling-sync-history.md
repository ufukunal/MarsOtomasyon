# G-908 — Webhook, polling, retry ve sync history

## Amaç

Inbound webhook/polling ve outbound/inbound sync olaylarını güvenli, izlenebilir ve yeniden denenebilir hale getirmek.

## Önkoşul

G-901…G-907.

## Dokunulacak dosyalar

- channel_sync_events/errors migrations/models
- webhook endpoints
- polling jobs/scheduler
- retry/manual retry Actions
- sync center UI
- sync tests

## Şema / Kod

Polling:

- 15 dakika,
- manual trigger.

Retry:

- 30 / 60 / 120 sn,
- sonra persistent error.

History:

- safe metadata
- payload hash
- correlation id
- error summary

## Kurallar

- Webhook destekleniyorsa primary.
- Polling güvenlik ağı.
- Duplicate pending same entity/action jobs coalesce.
- Hassas tam payload yok.
- Manuel retry yeni history/audit üretir.

## Kabul ölçütü

- Webhook event işleniyor.
- Polling kaçan order/cancel/return'ü yakalıyor.
- Retry cadence doğru.
- Üç başarısızlıktan sonra sync error oluşuyor.
- Manual retry çalışıyor.
- Sensitive full payload DB'de yok.

## İstem

> K-193…K-195/K-201 webhook, polling ve sync observability altyapısını uygula.
