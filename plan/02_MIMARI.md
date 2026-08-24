# 02 — Mimari V4.1

## Stil
**Laravel-native modular monolith.** Tek deployable uygulama, net domain sınırları. Microservice yok; gerektiğinde ileride ayrılabilecek modül sınırları korunur.

## Runtime
- PHP 8.5
- Laravel 13
- PostgreSQL 18
- Valkey
- Laravel Queue workers
- Laravel Scheduler
- OpenLiteSpeed/Nginx gibi web katmanı deploy ortamına göre seçilebilir; domain kodu web sunucusuna bağlı değildir

## Katmanlar
Her domain modülünde mümkün olduğunca:
- HTTP/UI adapter
- Application use-case/service
- Domain model/rules
- Persistence
- Events/outbox
- Tests

Gereksiz repository/interface kalabalığı kurulmaz. Eloquent domain doğruluğunu bozmadığı yerde doğrudan kullanılabilir.

## Modül sınırları
- Core/Identity/Settings
- Accounts
- Catalog/Inventory
- Sales
- Purchasing
- Treasury
- Instruments (çek/senet)
- Production/Subcontracting
- Returns
- Import
- Commerce/B2B
- Communication
- Reporting
- Files/Printing
- Operations/Migration

Modüller birbirinin tablolarını keyfi mutate etmez. Cross-domain değişimler application service + transaction/event ile yapılır.

## Authority ledger'ları
- Cari/clearing: `account_transactions`
- Stok: `stock_movements`
- Kasa/banka/POS: `treasury_movements`

Source belge tabloları balance authority değildir. Valkey/cache authority değildir.

## Transaction sınırı
Kullanıcı açısından tek business action olan değişimler tek DB transaction'dır.

### Örnek: sevkiyat posting
1. belgeyi lock/doğrula + posting period
2. dispatch state
3. order net dispatched/remaining progress
4. reservation consume/release
5. stock OUT + carrying cost
6. outbox gerekiyorsa
7. commit

### Örnek: bağlı satış faturası posting
1. invoice lock/doğrula + posting period
2. invoice state
3. order net invoiced/remaining progress
4. **stock effect yok; linked dispatch physical OUT authority'dir**
5. AccountTransaction
6. outbox
7. commit

### Örnek: irsaliyesiz direct invoice
Aynı transaction içinde invoice + order progress gerekiyorsa + AccountTransaction + **direct physical stock OUT** + outbox oluşur.

Yan etkiler (SMS/e-posta/provider webhook vb.) commit sonrası outbox/queue ile gönderilir.

## Source-effect matrix
Cross-domain use-case hangi ledger effect'ini ürettiğini explicit tanımlar. Aynı fiziksel/finansal event ikinci source üzerinden tekrar yazılmaz.

Örnek:
- Dispatch → StockMovement OUT
- linked SalesInvoice → AccountTransaction, no second stock OUT
- GoodsReceipt → StockMovement IN
- SupplierInvoice → AccountTransaction, no second stock IN
- Collection/Payment → AccountTransaction + TreasuryMovement/instrument effect
- Marketplace payout → clearing AccountTransaction + TreasuryMovement

## Idempotency
Posting, webhook, marketplace import/settlement ve external retry yollarında idempotency zorunludur. Aynı external event veya business command ikinci kez finans/stok/treasury etkisi üretemez.

## Concurrency
- kritik satırlarda row lock / optimistic version uygun yerde kullanılır
- stok/reservation, moving-average cost, sipariş miktarı, marketplace settlement ve çek/senet yarış koşulları test edilir
- benzersiz business/source-effect key'ler DB constraint ile korunur
- lock ordering deterministic tutulur

## Arama
V1: PostgreSQL FTS + `pg_trgm`. Ürün aramada kod/barkod/QR/ad; cari aramada kod/unvan/vergi/telefon/e-posta gibi gerçek alanlar.

## Cache
Valkey yalnız yeniden üretilebilir veri, rate limit, distributed lock, queue/session gibi amaçlarda kullanılır. Finans, treasury ve stok authority cache değildir.

## Dosyalar
Metadata PostgreSQL'de; binary dosyalar storage abstraction üzerinden local/S3-compatible hedefe konabilir. DB transaction ile dosya lifecycle arasında orphan cleanup stratejisi gerekir.

## Observability
Structured log + request/correlation id + queue/job context + audit trail. Finans/stok/treasury business audit'i application log'dan ayrı tutulur.

## Deployment
İlk hedef tek sunucu/az kullanıcı için sade kurulumdur. Horizontal scale için gereksiz altyapı önceden eklenmez. M0 CI foundation ve M23 production hardening uygulanmadan production-ready etiketi kullanılmaz.
