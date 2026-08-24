# 14 — Test, CI ve Kalite V4

## 1. Amaç
Test stratejisi finans/stok doğruluğu ile V16.3 kullanıcı akışını birlikte korur. Production-benzeri PostgreSQL 18 davranışı esastır.

## 2. Gate A — Fast local
Her application commit öncesi ilgili scope'ta:
- Composer install/lock doğrulaması
- Pint/formatter
- static analysis
- unit tests
- decimal/value/rule tests
- touched module focused feature tests
- frontend JS syntax/build checks

## 3. Gate B — Main correctness
Main update için ilgili scope'ta:
- gerçek PostgreSQL 18 integration tests
- company isolation
- authorization
- source-effect exactly-once
- cari balance algebra
- stock/order quantity caps
- reservation consume/release
- reversal/correction
- Outbox/Inbox idempotency
- touched-domain concurrency
- migration smoke/upgrade path

SQLite passing sonucu PostgreSQL yerine kabul edilmez.

## 4. Gate C — V16.3 UI regression
Touched kullanıcı-visible yüzey için browser/E2E acceptance:
- ana menu route açılıyor
- list/detail/edit/create route açılıyor
- workspace tab davranışı doğru
- görünür primary button route/action üretiyor
- dead/no-op button yok
- runtime JS error yok
- console/backend error yok
- `undefined/[object Object]` sızıntısı yok
- generic placeholder detail/form yok
- normal kullanıcıya teknik jargon yok
- finalized detail readonly
- duplicate kullanıcı-visible alan/sekme yok
- beklenmeyen yatay page overflow yok

## 5. Test piramidi
### Unit
Saf hesaplama, rounding, state transition, value object.

### Feature/Integration
Ana ağırlık: HTTP/use-case + PostgreSQL transaction + authorization + ledger effects.

### Browser/E2E
Yüksek değerli V16.3 akışları.

## 6. Cari testleri
- sales invoice balance increase
- collection balance decrease
- supplier invoice payable increase
- payment payable decrease
- over-collection/payment signed balance
- OpenItem/allocation schema/UX bulunmaması
- Alacaklı/Borçlu/Bakiye Yok mapping
- cross-company access block

## 7. Satış testleri
- quote revision immutability
- partial dispatch
- partial invoice
- remaining_to_dispatch/invoice
- reservation consume/release
- over-dispatch block
- over-invoice block
- duplicate invoice posting block
- KDV Sıfırla recalculation
- dispatch/invoice same physical stock effect duplicate değil
- finalized detail readonly

## 8. Alış testleri
- PO remaining receive/invoice
- over-receipt block
- over-invoice block
- GoodsReceipt stock effect exactly-once
- SupplierInvoice second stock IN üretmiyor
- Uygun/Kontrol Bekliyor/Uygun Değil
- invoice/receipt farklı tarihler
- finalized detail readonly

## 9. Stok testleri
- `stock_movements` authority
- available stock formula
- negative-stock policy
- transfer source issue / partial receipt / final reconcile
- stock count system/counted/difference
- count double-post guard
- no lot/serial core assumption

## 10. Kasa / Banka testleri
- collection/payment type-specific validation
- account + treasury atomikliği
- cash movement
- bank movement
- POS gross cari effect + separate commission
- net settlement second cari effect üretmiyor
- virman atomicity
- cash denomination/total/difference
- difference reason requirement
- statement duplicate guard
- reconciliation match no second movement
- unmatch/rematch history

## 11. Çek / Senet testleri
- received delivery reduces customer balance once
- later bank collection no second cari effect
- dishonored reversal reopens balance
- issued delivery reduces supplier payable once
- later bank payment no second cari effect
- unpaid/cancel reversal
- lifecycle/history/physical location/front-back file refs
- settlement concurrency

## 12. Üretim / Fason
- recipe quantity
- material issue stock OUT
- finished good receipt stock IN
- quantity cap
- close reconciliation
- fire/eksik handling
- fason sent/received/scrap-missing/remaining reconcile

Routing/work-center/ECO/OEE tests core değildir.

## 13. İthalat / maliyet
- container-product reconciliation
- same product multi-container lineage
- allocation source total = allocated total
- duplicate cost item allocation block
- deterministic rounding
- loading simulation business authority değil

## 14. E-Ticaret / B2B ortak testleri
- provider-account + external entity uniqueness
- inbound message dedupe
- webhook/poll retry duplicate order üretmiyor
- Mars stock authority
- publish stock formula incl. safety stock
- stale retry current desired state'i geriye götürmüyor
- provider raw status Mars SalesOrder state'ini keyfi overwrite etmiyor
- unsupported capability gerçek action gibi gösterilmiyor
- secret read-back masked
- connection-test permission/error handling
- rate-limit/backoff provider policy'ye uyuyor
- ambiguous response blind resend yapmıyor
- malformed provider payload business effect üretmiyor
- channel/account isolation
- B2B user pre-bound cari
- B2B başka cari göremiyor
- B2B order no cari effect
- invoice cari effect
- B2B discount = Cari İskontosu

## 15. Marketplace adapter contract test suite
Aşağıdaki ortak contract suite her **aktif V1 marketplace adapterına** uygulanır:
1. credential schema + secret masking
2. connection test success/failure
3. capability discovery/static matrix
4. product/listing identity mapping
5. stock desired-state publish idempotency
6. price publish normalization/rounding
7. order import normalization
8. duplicate order/event guard
9. shipment/cancel/return capability mapping
10. provider error → normalized problem record
11. retry/backoff/rate-limit
12. timeout/ambiguous outcome reconcile
13. polling cursor/watermark restart safety
14. webhook replay safety where supported
15. raw payload redaction/retention policy
16. API version/deprecation fixture compatibility

### V1 provider-specific suites
- WooCommerce
- Trendyol
- Hepsiburada
- Amazon SP-API
- n11
- PttAVM
- idefix
- Allesgo

Her adapter için provider contract fixture/sample payload'ları repository test fixture'larında versionlanır. Production credential CI'a yazılmaz.

### Amazon ekstra testleri
- marketplace/region scope
- LWA/token lifecycle abstraction
- listing/catalog identity
- FBA/FBM capability ayrımı
- Orders/shipment normalization
- report/settlement evidence import idempotency

### Diğer aktif marketplace adapterları ekstra testleri
Hepsiburada, n11, idefix, PttAVM ve Allesgo için provider'ın batch/task/pagination/rate-limit/status davranışları fixture ile test edilir; provider'ın desteklemediği operasyonlar `unsupported/manual` olarak deterministic davranır.

### Deferred adaylar
Çiçeksepeti, Pazarama, Koçtaş, Teknosa, Temu Türkiye ve Boyner V1 adapter test zorunluluğu değildir. Bu kanallar için fixture/contract suite ancak gerçek API veya seller/partner contract erişimi doğrulandıktan sonra açılır.

## 16. API / Security
- same idempotency key + different payload conflict
- CSRF/authorization/rate-limit critical paths
- webhook signature/replay
- cross-company leak tests
- private file authorization
- upload validation/quarantine where enabled
- secret/PII redaction

## 17. Search
PostgreSQL FTS + `pg_trgm`:
- SKU/barcode exact/prefix
- Turkish product/contact name
- typo/partial trigram
- `I/İ/ı/i` cases
- company isolation
- search result authorization re-check

## 18. Reports / Print
- ready report routes where implemented
- filter consistency
- runtime permission for scheduled report
- Excel/CSV export
- PDF/print
- no action column/browser scrollbar in print

## 19. Migration / import/export
- dry-run
- row validation/error report
- duplicate/restart idempotency
- malformed input
- CSV formula injection
- large-file chunking/memory limits
- historical import external side-effect count = 0
- reconciliation

## 20. Backup / restore
Release/pre-go-live:
- backup creation
- isolated restore drill
- DB/files/checksum/version verification
- critical smoke
- sequence/external mapping integrity
- recovery mode barrier

## 21. Heavy / pre-release
- full regression
- risky migration rehearsal
- projection rebuild/reconciliation
- provider timeout/ambiguous response
- marketplace adapter fixture suite
- selected sandbox/test-account smoke
- large import/export
- UI route crawl
- performance indexes/query plans for touched hotspots

## 22. Merge / main kuralı
Kırmızı CI ile main değişikliği tamamlanmış sayılmaz. Flaky test disable edilmez; kök neden düzeltilir. Documentation-only commit application full suite'i zorunlu kılmaz.
