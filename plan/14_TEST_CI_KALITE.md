# 14 — Test, CI ve Kalite V4.2

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
- cari balance algebra + currency guard
- stock/order quantity caps
- reservation consume/release/oversell guard
- reversal/correction
- Outbox/Inbox idempotency
- treasury ledger integrity
- moving-average cost integrity
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

## 5. M0 infrastructure gate
Application bootstrap tamamlanmış sayılmaz unless:
- Laravel/PHP dependency install çalışıyor
- PostgreSQL 18 CI service çalışıyor
- Valkey smoke çalışıyor
- formatter/static-analysis command tanımlı
- unit + integration test workflow var
- browser/E2E skeleton route-smoke çalışıyor
- dependency advisory/security check var
- GitHub required status checks isimleri belirlenmiş
- `main` branch protection/required checks uygulanabiliyorsa aktif; teknik/plan kısıtı varsa açık blocker olarak kaydedilmiş

## 6. Cari testleri
- sales invoice balance increase
- collection balance decrease
- supplier invoice payable increase
- payment payable decrease
- over-collection/payment signed balance
- Account book currency mismatch BLOCK
- base amount/rate snapshot
- OpenItem/allocation schema/UX bulunmaması
- Alacaklı/Borçlu/Bakiye Yok mapping
- cross-company access block

## 7. Tax / pricing testleri
- net/KDV-hariç product price authority
- KDV hariç input → doğru net/tax/gross
- KDV dahil input → deterministic net back-calc
- line discount before tax
- document discount deterministic line allocation
- line tax sum = document tax total
- explicit rounding difference
- `KDV Sıfırla` recalculation
- zero-tax reason code required where applicable
- posted tax snapshot master değişince değişmiyor

## 8. Satış testleri
- quote revision immutability
- partial dispatch
- partial invoice
- net reversal-safe dispatched/invoiced counters
- remaining_to_dispatch/invoice reversal sonrası reopen
- reservation consume/release
- reservation > available BLOCK
- over-dispatch BLOCK
- over-invoice BLOCK
- over-cancel BLOCK
- over-return BLOCK
- duplicate invoice posting block
- dispatch stock OUT authority
- dispatch→invoice second stock OUT yok
- irsaliyesiz direct invoice stock OUT
- finalized detail readonly

## 9. Alış / Mal Kabul testleri
- PO remaining receive/invoice
- accepted/pending/rejected split sum = physical received
- pending/rejected available stock'a girmez
- PO received progress accepted quantity ile kapanır
- pending→accepted reclassify second stock IN üretmez
- pending→rejected reclassify second stock IN üretmez
- over-acceptance BLOCK
- over-invoice BLOCK
- GoodsReceipt stock effect exactly-once
- SupplierInvoice second stock IN üretmiyor
- invoice/receipt farklı tarihler
- finalized detail readonly

## 10. Stok / costing testleri
- `stock_movements` authority
- available stock formula
- negative stock BLOCK
- reservation oversell BLOCK
- transfer source issue → in-transit → partial/full receipt
- transfer quantity/value company totalinde kaybolmuyor
- transfer P/L üretmiyor
- moving-average inbound update
- moving-average outbound carrying value
- positive stock count current average
- cost yoksa positive count valuation required
- no zero-cost positive inventory
- stock count double-post guard
- no lot/serial core assumption

## 11. Kasa / Banka / Treasury testleri
- `treasury_movements` balance authority
- source record direct balance mutation yapmıyor
- collection/payment type-specific validation
- account + treasury atomicity
- cash movement
- bank movement
- POS gross cari effect + separate commission
- POS Pending→Settled banka movement
- net settlement second cari effect üretmiyor
- chargeback/reversal path
- virman atomicity
- cash denomination/total/difference
- difference reason requirement
- statement duplicate guard
- reconciliation match no second movement
- unmatch/rematch history

## 12. Çek / Senet testleri
- received delivery reduces customer balance once
- later bank collection no second cari effect
- issued delivery reduces supplier payable once
- later bank payment no second cari effect
- received instrument endorsement reduces supplier payable once
- endorsement does not repeat customer effect
- dishonored endorsed instrument supplier reversal + required customer reversal chain
- unpaid/cancel reversal
- lifecycle/history/physical location/front-back file refs
- settlement/endorsement concurrency

## 13. Üretim / Fason
- recipe quantity
- material issue stock OUT + carrying cost
- finished good receipt stock IN + allocated cost
- quantity cap
- close reconciliation
- fire/eksik handling
- subcontract sent quantity/value custody
- subcontract received/scrap-missing/remaining reconcile
- custody value company inventory'den kaybolmuyor

Routing/work-center/ECO/OEE tests core değildir.

## 14. İthalat / maliyet
- container-product reconciliation
- same product multi-container lineage
- import acceptance kendi başına stock movement üretmiyor
- GoodsReceipt/ImportReceipt handoff exactly-once stock IN
- allocation source total = allocated total
- duplicate cost item allocation block
- deterministic rounding
- late landed cost original receipt/import lineage
- loading simulation business authority değil

## 15. Marketplace / B2B ortak testleri
- provider-account + external entity uniqueness
- inbound message dedupe
- webhook/poll retry duplicate order üretmiyor
- legal customer snapshot zorunlu Account yaratmıyor
- clearing account mapping
- marketplace invoice clearing receivable
- payout clearing receivable + treasury bank movement
- commission/fee separate effect
- duplicate settlement row second finance effect üretmiyor
- clearing reconciliation formula
- Mars stock authority
- publish stock formula incl. safety stock
- stale retry current desired state'i geriye götürmüyor
- unavailable stock → `Sorun/Stok Eksik`, negative stock yok
- provider raw status Mars SalesOrder state'ini keyfi overwrite etmiyor
- unsupported capability gerçek action gibi gösterilmiyor
- media capability unsupported ise no-op yok
- secret read-back masked
- connection-test permission/error handling
- rate-limit/backoff provider policy'ye uyuyor
- ambiguous response blind resend yapmıyor
- malformed provider payload business effect üretmiyor
- channel/account isolation

### B2B auth/security
- B2BUser internal User değildir
- B2B login/logout/password reset/activation
- brute-force/rate-limit
- session/token revoke
- pre-bound cari değiştirilemiyor
- başka cari görülemiyor
- internal admin route/API erişilemiyor
- B2B order no cari effect
- invoice cari effect
- B2B discount = Cari İskontosu
- risk/exposure server-side block/warning policy

## 16. Marketplace adapter contract test suite
Aşağıdaki ortak contract suite her **aktif V1 marketplace adapterına** uygulanır:
1. provider registry metadata mevcut
2. credential schema + secret masking
3. connection test success/failure
4. capability discovery/static matrix
5. product/listing identity mapping
6. media/image publish capability mapping
7. stock desired-state publish idempotency
8. price publish normalization/rounding/tax gross conversion
9. order import normalization
10. duplicate order/event guard
11. shipment/cancel/return capability mapping
12. settlement/payout evidence normalization where supported
13. provider error → normalized problem record
14. retry/backoff/rate-limit
15. timeout/ambiguous outcome reconcile
16. polling cursor/watermark restart safety
17. webhook replay safety where supported
18. raw payload redaction/retention policy
19. API version/deprecation fixture compatibility

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

### Deferred adaylar
Çiçeksepeti, Pazarama, Koçtaş, Teknosa, Temu Türkiye ve Boyner V1 adapter test zorunluluğu değildir. Fixture/contract suite gerçek API veya seller/partner contract erişimi doğrulandıktan sonra açılır.

## 17. Posting period / security
- open period posting success
- closed/frozen period normal user BLOCK
- override permission + reason + audit
- approval policy aktifse approval required
- document_date değiştirerek posting-period bypass yok
- same idempotency key + different payload conflict
- CSRF/authorization/rate-limit critical paths
- webhook signature/replay where supported
- cross-company leak tests
- private file authorization
- upload validation/quarantine where enabled
- secret/PII redaction

## 18. Search
PostgreSQL FTS + `pg_trgm`:
- SKU/barcode exact/prefix
- Turkish product/contact name
- typo/partial trigram
- `I/İ/ı/i` cases
- company isolation
- search result authorization re-check

## 19. Reports / Print
- ready report route/catalog ID exists
- filter consistency
- runtime permission for scheduled report
- Excel/CSV export
- PDF/print
- no action column/browser scrollbar in print
- report catalog implemented count vs expected milestone count

## 20. Migration / import/export
- dry-run
- row validation/error report
- duplicate/restart idempotency
- malformed input
- CSV formula injection
- large-file chunking/memory limits
- historical import external side-effect count = 0
- reconciliation
- all enabled channel cutover cursor/watermark handling

## 21. Backup / restore
Release/pre-go-live:
- backup creation
- isolated restore drill
- DB/files/checksum/version verification
- critical smoke
- sequence/external mapping integrity
- recovery mode barrier

## 22. Heavy / pre-release
- full regression
- risky migration rehearsal
- projection rebuild/reconciliation
- provider timeout/ambiguous response
- marketplace adapter fixture suite
- selected sandbox/test-account smoke
- marketplace clearing reconciliation
- large import/export
- UI route crawl
- performance indexes/query plans for touched hotspots

# 23. Planlı M25–M31 genişleme testleri

## M25 Product Family / Variant
- simple Product family olmadan çalışıyor
- existing Product ID/SKU migration sonrası aynı
- ProductFamily duplicate/invalid membership guard
- dimension/value uniqueness
- family stock/price/cost authority değil
- variant search/media behavior
- marketplace parent/child mapping
- family delete/archive SKU history silmiyor

## M26 Barkod / Termal Etiket
- label payload doğru product/barcode/location kaynağından geliyor
- A4/PDF render smoke
- ZPL/TSPL fixture/snapshot where implemented
- cross-company label access BLOCK
- reprint business ledger mutate etmiyor
- printer unavailable/error path

## M27 Mobil Depo / Scanner
- mobile API same authorization/invariant yolunu kullanıyor
- stable client operation id duplicate effect üretmiyor
- repeated scan policy deterministic
- GoodsReceipt/dispatch/transfer/count source-effect exactly-once
- scanner focus/error/retry browser/PWA tests
- wrong company/warehouse access BLOCK
- offline write açık değilse network loss local fake success göstermiyor

## M28 Kargo API Adapterları
- provider registry/credential/capability
- shipment create idempotency
- timeout → query-before-retry/ambiguous reconcile
- label artifact authorization
- tracking event dedupe
- provider status Mars Dispatch'i keyfi overwrite etmiyor
- cancel/return capability mapping
- unsupported/manual deterministic
- secret/redaction/rate-limit

## M29 OCR Fatura / Dekont
- supported file/type/size validation
- OCR provider/model/version evidence
- extracted field + confidence
- low confidence review required
- cari/ürün/banka match sadece suggestion
- duplicate file/result second posting üretmiyor
- OCR output direct AccountTransaction/StockMovement/TreasuryMovement yazamıyor
- reviewed result normal domain validation'dan geçiyor
- PII/attachment authorization

## M30 Hafif CRM
- Lead/Opportunity company scope
- stage transition/history
- Activity/FollowUp permissions
- Lead → Account conversion duplicate guard
- Quote linkage
- closed/lost opportunity finance/stock effect üretmiyor
- Account master duplicate edilmemiş

## M31 BI Export
- dataset schema version
- company scope + PII allow-list
- curated dataset authoritative report totals ile reconcile
- scheduled export runtime authorization
- incremental watermark restart-safe where used
- partial/failed export observable
- expired artifact access BLOCK
- BI write-back yok

## 24. Merge / main kuralı
Kırmızı CI ile main değişikliği tamamlanmış sayılmaz. Flaky test disable edilmez; kök neden düzeltilir. Documentation-only commit application full suite'i zorunlu kılmaz.
