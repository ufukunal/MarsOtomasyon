# Commerce / B2B / API — Detailed Plan

Status: DETAILED PLANNING FROZEN

## V38 product surface
Pazaryeri Merkezi, Provider Hesapları, WooCommerce Siparişleri, Trendyol Siparişleri, Ürün/Kanal Eşleştirme, Pazaryeri Hakedişleri, Sync Hataları, Inbound Inbox, External Entity Mapping, Kill Switch, API İstemcileri, API Idempotency, B2B Kullanıcıları, B2B Siparişleri, Müşteri Teklif Portalı.
Portfolio also names Hepsiburada, N11, ÇiçekSepeti and Idefix.

## Ownership
Commerce owns provider accounts, mappings, synchronization, inbound normalization, reconciliation evidence and portals. Mars Product/Sales/Returns/Finance remain authoritative business modules.

## Records
ProviderAccount, ExternalEntityMap, ChannelProductOverride, ChannelPricePublication, ChannelStockPublication, InboundEnvelope, ExternalOrderMap, SyncAttempt/Error, WebhookReceipt, ProviderCursor, KillSwitch, ApiClient, ApiIdempotencyRecord, B2BUserMembership, PortalSubmission, MarketplaceSettlementEvidence.

## Workflow
Inbound: RECEIVED -> VERIFIED -> DEDUPED -> MAPPED -> APPLIED | MANUAL_REVIEW | REJECTED.
Outbound: PENDING -> SENT -> ACKNOWLEDGED | RETRYABLE_FAILURE | MANUAL_REVIEW.
Provider account ACTIVE/SUSPENDED/DISABLED. Kill switch blocks mutation/delivery for target scope.

## Effects
Provider data never directly owns STOCK/ACCOUNT/CASH/COST. External order becomes a normal Sales command/document. Returns normalize to Returns. Settlement evidence is reconciled by Finance.

## Rules
External ids unique per provider/account/entity type. Webhook signature/secret verification mandatory. Durable inbox/outbox/idempotency. Polling/reconciliation catches missed events. Provider retries cannot duplicate Mars business commands. Sellable stock is a projection, not authority.

## Provider contract
For each provider: auth, rate limits, catalog, price, stock, order, shipment, cancellation, return, questions/messages, invoice/e-document capability, webhook/polling, retries, reconciliation, sandbox, error model. Current capability must be web-verified at implementation time.

## API/B2B
API clients use scoped credentials/permissions and idempotency. B2B users map to trusted Party/company scope. B2B order/quote submits through Sales; portal never bypasses policy/approval.

## UI
V38 screens retained conceptually: center, accounts, mappings, errors/inbox, settlements evidence, client/idempotency, B2B users/orders, portal quote.

## Acceptance
Webhook auth, dedupe, mapping, replay, kill switch, retry safety, provider/Mars authority boundary, cross-company isolation, API client scope, B2B Party scope.

## UNKNOWN
Exact provider endpoint capabilities and commercial settlement schemas must be verified against current provider documentation at implementation time.
