# M18 — Verified Marketplace Adapter Pack Contract Matrix

> Durum modeli: `contract_verified` = resmi/güncel provider sözleşmesi kod ve fixture testleriyle kilitli. `verified_marketplace` yalnız gerçek merchant credential ile SIT/production kanıtı mevcutsa kullanılabilir. Bu milestone production credential varmış gibi davranmaz.

M18 V1 **kod exit'i tamamlanmıştır**. Bu durum provider hesabının canlı olarak doğrulandığı anlamına gelmez; aşağıdaki adapter status'ları gerçek merchant/SIT/production kanıtı oluşana kadar `contract_verified` kalır.

| Provider | Auth / ortam | Katalog / ürün | Stok-fiyat | Sipariş | Async / operasyon | M18 kod durumu |
| --- | --- | --- | --- | --- | --- | --- |
| Trendyol | API key/secret, storefront, stage/prod | Product V2 contract | `items[].barcode`, quantity, salePrice/listPrice | V2 package polling + webhook | `batchRequestId`, 15 dk duplicate cooldown | `contract_verified` |
| Hepsiburada | Basic Auth + zorunlu User-Agent, SIT/prod | listing + MPOP status | inventory upload contract | paid orders + order detail | inventory upload result | `contract_verified` |
| Amazon SP-API | LWA access/refresh token, EU/NA/FE, sandbox/prod | Listings Items + Product Type Definitions | Listings patch; FBM stock, FBA Amazon-authoritative | Orders API `2026-01-01`, `orderId`, `paginationToken` | Reports API for settlement/returns | `contract_verified` |
| n11 | `appkey` + `appsecret` | category/attribute + product task | `/ms/product/tasks/price-stock-update` | `/rest/delivery/v1/shipmentPackages`; inbound line identity `stockCode` | task detail/page query | `contract_verified` |
| PttAVM | `Api-Key` + `Access-Token` + `X-Correlation-Id` | REST category/product contracts | `/api/v1/products/stock-prices` | REST order search/detail | tracking result | `contract_verified` |
| idefix | `X-API-KEY`, stage/prod credentials ayrı | category/attribute/brand + product create | vendor inventory upload | vendor shipment list | `batchRequestId` result | `contract_verified` |
| Allesgo | access token + store id, sandbox/prod | v1.0 product/variant contracts | stock + kuruş bazlı price | store order endpoint | provider response identity | `contract_verified` |

## Deterministic capability exit profili

`MarketplaceCapabilityContract` provider registry ile aynı capability anahtarlarını fail-closed doğrular. Desteklenmeyen operasyonlar API varmış gibi gösterilmez; `manual`, `evidence`, `unsupported` ve `api_contract` modları açıkça ayrılır.

| Provider | Media | Cancel | Return | Questions | Invoice | Settlement | Test/smoke modu |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Trendyol | `manual` | `api_contract` | `api_contract` | `api_contract` | `api_contract` | `api_contract` | `stage_contract` |
| Hepsiburada | `manual` | `unsupported` | `api_contract` (claim) | `unsupported` | `api_contract` | `evidence` | `sit_contract` |
| Amazon SP-API | `schema_driven` | `unsupported` | `evidence` (returns report) | `unsupported` | `unsupported` | `evidence` (settlement report) | `sandbox_contract` |
| n11 | `product_contract` | `unsupported` | `evidence` | `unsupported` | `api_contract` | `evidence` | `fixture_contract` |
| PttAVM | `product_contract` | `unsupported` | `evidence` | `unsupported` | `api_contract` | `evidence` | `fixture_contract` |
| idefix | `product_contract` | `api_contract` | `api_contract` | `api_contract` | `api_contract` | `evidence` | `fixture_contract` |
| Allesgo | `product_contract` | `unsupported` | `evidence` | `api_contract` | `api_contract` | `evidence` | `sandbox_contract` |

Bu profil `M18ProviderCapabilityContractTest` ile registry drift'ine karşı korunur. `unsupported` bir capability eksikliği saklamaz; provider için doğrulanmış sözleşmede bu operasyonun bu adapter tarafından API aksiyonu olarak sunulmadığını deterministik biçimde ifade eder.

## Ortak lifecycle

`MarketplacePackService` provider adapterlarını mevcut M17/M18 entegrasyon omurgasına bağlar:

- connection credentials `integration_connections.credentials_ciphertext` içinde şifreli tutulur;
- mapping kimliği provider sözleşmesine göre SKU/barcode/product/variant kimliğine çözülür;
- desired-state `channel_listing_states.desired_version` ile versionlanır;
- eski effect yeni desired version karşısında `stale desired-state version` ile `ignored` olur;
- aynı payload kısa cooldown içinde yeniden gönderilmez;
- 429 cevaplarında `Retry-After` bounded retry window'a çevrilir;
- transport connection failure sonucu `ambiguous` kabul edilir; kör retry ile çift dış etki üretilmez;
- malformed order listesi partial event üretmeden fail-closed reddedilir;
- order polling sonuçları `ChannelEventStore` üzerinden provider identity + payload hash ile idempotent persist edilir;
- otomatik polling window/cursor `integration_connections.order_poll_watermark_at` + `order_poll_cursor` ile restart-safe persist edilir;
- HB/n11/idefix sayfa cursor'ı window tamamlanmadan watermark'ı ilerletmez; Amazon Orders `2026-01-01` `pagination.nextToken` değerini sonraki çağrıda `paginationToken` olarak aynı window filtreleriyle kullanır;
- manuel `modifiedAfter` replay kalıcı otomatik cursor/watermark'ı ilerletmez;
- n11 order satırındaki `stockCode` ortak inbound SKU kimliği olarak çözülür;
- downstream stok rezervasyon hatası mevcut Channel Domain / Problem Center hattında `channel_problems` kaydına dönüşür;
- duplicate order/effect akışları mevcut idempotency ve desired-state cooldown testleriyle korunur.

## M18 kod exit kanıtları

- PR #77: Trendyol contract adapter ve provider-specific operasyon sözleşmeleri.
- PR #80: Hepsiburada, Amazon SP-API, n11, PttAVM, idefix ve Allesgo adapter pack + ortak lifecycle.
- PR #83: malformed fixture fail-closed davranışı ve MarketplacePack → domain ingestion → Problem Center PostgreSQL entegrasyon kanıtı.
- PR #84: n11 gerçek inbound `stockCode` ürün kimliği ile domain mapping hizalaması.
- `MarketplaceCapabilityContract` + `M18ProviderCapabilityContractTest`: provider-specific media/manual, cancel/return/questions/invoice/settlement ve smoke-mode boundary'si.
- `MarketplaceOrderPollCursor` + `M18MarketplacePollingCursorTest`: n11 page resume, Amazon `paginationToken`, stable polling window, final watermark advance ve restart-safe idempotent ingestion.

## Resmî sözleşme kaynakları

- Trendyol Developer / Product V2 ve Order V2 dokümantasyonu
- Hepsiburada Developers: `https://developers.hepsiburada.com/`
- Amazon Selling Partner API: `https://developer-docs.amazon.com/sp-api/`
- n11 Mağaza Destek / Developer: `https://magazadestek.n11.com/`, `https://developer.n11.com/`
- PttAVM Developers: `https://developers.pttavm.com/tr`
- idefix Developer: `https://developer.idefix.com/`
- Allesgo Developers v1.0: `https://developers.allesgo.com/`

## Production verification boundary

Bu dosyanın varlığı, M18 kod milestone'unun `DONE` olması veya CI'ın yeşil olması gerçek marketplace hesabının onaylandığı anlamına gelmez. Merchant credential, whitelist/IP, mağaza yetkisi, sandbox/SIT erişimi veya canlı çağrı gerektiren kontroller provider/account bazında ayrıca kanıtlanır. Bu kanıt olmadan registry status `verified_marketplace` yapılmaz.
