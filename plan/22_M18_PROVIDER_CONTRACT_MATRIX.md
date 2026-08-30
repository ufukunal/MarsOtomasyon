# M18 — Verified Marketplace Adapter Pack Contract Matrix

> Durum modeli: `contract_verified` = resmi/güncel provider sözleşmesi kod ve fixture testleriyle kilitli. `verified_marketplace` yalnız gerçek merchant credential ile SIT/production kanıtı mevcutsa kullanılabilir. Bu milestone production credential varmış gibi davranmaz.

| Provider | Auth / ortam | Katalog / ürün | Stok-fiyat | Sipariş | Async / operasyon | M18 kod durumu |
| --- | --- | --- | --- | --- | --- | --- |
| Trendyol | API key/secret, storefront, stage/prod | Product V2 contract | `items[].barcode`, quantity, salePrice/listPrice | V2 package polling + webhook | `batchRequestId`, 15 dk duplicate cooldown | `contract_verified` |
| Hepsiburada | Basic Auth + zorunlu User-Agent, SIT/prod | listing + MPOP status | inventory upload contract | paid orders + order detail | inventory upload result | `contract_verified` |
| Amazon SP-API | LWA access/refresh token, EU/NA/FE, sandbox/prod | Listings Items + Product Type Definitions | Listings patch; FBM stock, FBA Amazon-authoritative | Orders API `2026-01-01` | Reports API for settlement/returns | `contract_verified` |
| n11 | `appkey` + `appsecret` | category/attribute + product task | `/ms/product/tasks/price-stock-update` | `/rest/delivery/v1/shipmentPackages` | task detail/page query | `contract_verified` |
| PttAVM | `Api-Key` + `Access-Token` + `X-Correlation-Id` | REST category/product contracts | `/api/v1/products/stock-prices` | REST order search/detail | tracking result | `contract_verified` |
| idefix | `X-API-KEY`, stage/prod credentials ayrı | category/attribute/brand + product create | vendor inventory upload | vendor shipment list | `batchRequestId` result | `contract_verified` |
| Allesgo | access token + store id, sandbox/prod | v1.0 product/variant contracts | stock + kuruş bazlı price | store order endpoint | provider response identity | `contract_verified` |

## Ortak lifecycle

`MarketplacePackService` kalan provider adapterlarını mevcut M17/M18 entegrasyon omurgasına bağlar:

- connection credentials `integration_connections.credentials_ciphertext` içinde şifreli tutulur;
- mapping kimliği provider sözleşmesine göre SKU/barcode/product/variant kimliğine çözülür;
- desired-state `channel_listing_states.desired_version` ile versionlanır;
- eski effect yeni desired version karşısında `stale desired-state version` ile `ignored` olur;
- aynı payload kısa cooldown içinde yeniden gönderilmez;
- 429 cevaplarında `Retry-After` bounded retry window'a çevrilir;
- transport connection failure sonucu `ambiguous` kabul edilir; kör retry ile çift dış etki üretilmez;
- order polling sonuçları `ChannelEventStore` üzerinden provider identity + payload hash ile idempotent persist edilir;
- downstream stock/account/order problem akışı mevcut Channel Domain / Problem Center hattında kalır.

## Resmî sözleşme kaynakları

- Trendyol Developer / Product V2 ve Order V2 dokümantasyonu
- Hepsiburada Developers: `https://developers.hepsiburada.com/`
- Amazon Selling Partner API: `https://developer-docs.amazon.com/sp-api/`
- n11 Mağaza Destek / Developer: `https://magazadestek.n11.com/`, `https://developer.n11.com/`
- PttAVM Developers: `https://developers.pttavm.com/tr`
- idefix Developer: `https://developer.idefix.com/`
- Allesgo Developers v1.0: `https://developers.allesgo.com/`

## Production verification boundary

Bu dosyanın varlığı veya CI'ın yeşil olması gerçek marketplace hesabının onaylandığı anlamına gelmez. Merchant credential, whitelist/IP, mağaza yetkisi, sandbox/SIT erişimi veya canlı çağrı gerektiren kontroller provider/account bazında ayrıca kanıtlanır. Bu kanıt olmadan registry status `verified_marketplace` yapılmaz.
