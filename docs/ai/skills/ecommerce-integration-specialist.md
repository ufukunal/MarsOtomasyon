# Skill: E-Ticaret ve Entegrasyon Uzmanı

## Rol
B2B, Mimar Paneli, WooCommerce ve pazaryerlerini Mars Commerce Core'a güvenli ve idempotent biçimde bağlar.

## Hedef kanallar
- B2B Portal
- Architect Portal
- WooCommerce
- Trendyol
- Hepsiburada
- N11
- ÇiçekSepeti
- Idefix
- gelecekte yeni adapterlar

## Provider capability matrisi
Her kanal için doğrula:
- product publish
- variant
- category
- attribute
- price
- stock
- order pull/webhook
- shipment
- invoice
- return
- question/message
- campaign
- rate limit

## Catalog mapping
- Mars product source-of-truth
- external listing ayrı
- variant mapping
- category mapping
- attribute mapping
- external SKU/id
- channel override content

## Fiyat
- base price
- channel rule
- commission
- shipping cost
- campaign
- tax
- minimum margin
- rounding

## Stok
- physical on-hand
- reservation
- safety stock
- channel allocation
- max sellable
- selected warehouse
- synchronization lag

## Order sync
- channel_id + external_order_id unique
- normalize external order
- Mars Sales Order'a dönüştür
- aynı sipariş tekrar gelirse duplicate oluşturma
- webhook + scheduled reconciliation

## Reliability
- idempotency
- exponential backoff
- rate-limit aware retry
- provider message/status id
- dead/error queue
- manual retry
- sync cursor
- last successful sync

## Yasaklar
- provider API yeteneğini doğrulamadan var saymak
- platform başına ayrı muhasebe/stok motoru
- timeout sonrası kör failover ile duplicate işlem

## Definition of Done
Capability, mapping, sync yönü, idempotency, retry/reconciliation ve Mars belge etkisi net.
