# 16 — Uygulama Sırası ve Milestone'lar V4.2

Amaç V16.3 tasarımını gerçek Laravel/PostgreSQL uygulamasına **küçük, çalışan, test edilmiş dikey dilimler** halinde dönüştürmektir. Eski repodaki büyük-bang geliştirme riski tekrar edilmez.

## Genel entry/exit kuralı
Her milestone başlamadan `19_ACIK_KARARLAR.md` içindeki kendi entry gate'i kontrol edilir.

Her teslim:
`entry gate → schema → domain use-case/action → transaction/invariant → authorization → V16.3 UI → tests → PostgreSQL CI → audit/observability`.

Bir milestone içindeki görünür butonlar gerçek route/action çalıştırmadan tamamlanmış sayılmaz. Future capability için kullanılmayan interface/table/framework önceden kurulmaz.

Gelecek özellik ekleme yolu ayrıca `27_GELECEK_GENISLEME_ALTYAPISI.md` içindeki activation checklist'e uyar.

# Wave A — Foundation

## M0 — Repository / Laravel / PostgreSQL / CI Foundation
- PHP 8.5 / Laravel 13
- PostgreSQL 18
- Valkey
- Composer lock
- Node/package-manager sürümü repo tarafından pinlenmiş frontend build skeleton
- Pint/formatter
- seçilmiş PHP static-analysis tool + baseline level
- unit/integration test commands
- browser/E2E route-smoke skeleton
- `.env.example` ve secret policy
- modular monolith skeleton
- migration smoke test
- health endpoint
- Clock/correlation temel altyapısı
- transactional Outbox skeleton
- PostgreSQL FTS + `pg_trgm`
- GitHub Actions: PHP install/cache + PostgreSQL 18 service + Valkey smoke + formatter + static analysis + tests + dependency advisory
- required status-check isimleri belirlenmiş
- `main` branch protection/required checks uygulanabiliyorsa aktif; uygulanamıyorsa açık repo-operasyon blocker'ı kayıtlı
- V16.3 textual contract yanında immutable design/reference artifact veya source/hash referansı versionlanmış
- provider key/family naming convention
- typed capability naming convention
- versioned internal event-name/schema convention
- stable source-effect/external identity naming convention
- code/config based `FeatureKey` registry convention

**M0 future-ready sınırı:** Yukarıdaki convention'lar için gerçek consumer yoksa boş DB tabloları/plugin framework/interface ağacı kurulmaz.

**M0 exit:** fresh clone → install → migrate → test → CI green zinciri çalışmadan M1 başlamaz.

## M1 — Core / Company / Users / Settings / UI Shell
- auth/session
- Company/Branch context
- user/role/permission
- base currency/timezone
- document numbering baseline
- tax/exchange-rate/posting-period basics
- audit
- Files foundation
- V16.3 sidebar/topbar/workspace tabs/global search/command palette
- FeatureKey availability ile henüz tamamlanmamış future modüllerin dead menu/route üretmemesi

## M2 — Cari Core
- Account tek book currency
- cari list/create/detail/edit
- Firma / Ticari
- İletişim / Yetkililer
- Sevk / Adres + manuel Ambar/Nakliye
- bank info
- notes/files
- risk limit / cari discount
- B2B access foundation metadata
- `account_transactions`
- balance/statement
- Alacaklı/Borçlu/Bakiye Yok

OpenItem/allocation yok.

# Wave B — Product / Stock / Sales / Purchase

## M3 — Ürün / Katalog
- product/category/unit/barcode
- tek net satış + tek net alış fiyatı
- KDV/tax metadata foundation
- readonly detail + separate edit
- supplier relation
- technical info file
- media foundation
- PostgreSQL product search
- Product identity/SKU modeli ileride `ProductFamily/VariantRelation` additive grouping eklenmesini engellemeyecek; family V1 tablosu zorunlu değil

Lot/serial ve generic price-list yok.

## M4 — Stok / Depo / Cost Foundation
Locked: negatif stok BLOCK, moving weighted average, reservation cap, transit custody.

- warehouse/location
- `stock_movements`
- balances/availability
- moving-average carrying cost
- reservation
- movement list
- transfer: source issue → in-transit → partial/full receipt
- stock count + positive adjustment valuation
- barcode Quick Count

Transfer/sayım ekranlarında cari/fiyat/KDV yok.

## M5 — Teklifler / Tax Calculation Contract
- quote list/create/detail
- revisions
- net/gross/tax/discount deterministic calculator
- KDV dahil/hariç input normalization
- document discount allocation
- tax zero reason
- approved revision → order
- fixed/versioned PDF
- readonly finalized detail

## M6 — Satış Siparişleri
- order list/create/detail
- product search code/barcode/QR/name
- reservation with available-stock guard
- ordered/net-dispatched/net-invoiced/cancelled/remaining
- cancellable/returnable helper rules
- over-operation guards
- KDV Sıfırla + zero-reason validation
- partial/reversal-safe flows

## M7 — İrsaliye / Sevkiyat
Locked default: physical stock OUT authority.

- dispatch list/create/detail
- shipment/address + manual carrier/warehouse suggestion
- previous/current/remaining shipment quantities
- stock OUT exactly-once
- reservation consume/release
- reversal reopens net progress
- readonly finalized detail

Fiyat/KDV ana odak değildir. Kargo API yalnız A-12 kapanırsa ayrı slice'tır. Gelecek shipping provider family contract'ı `27`ye uyar.

## M8 — Satış Faturaları
- invoice list/create/detail
- direct/order/dispatch lineage
- price/discount/tax totals
- customer/legal snapshot
- KDV Sıfırla + tax-zero reason
- order net invoiced/remaining progress
- account effect exactly-once
- dispatch-linked invoice second stock OUT üretmez
- irsaliyesiz direct invoice own stock OUT
- fixed/versioned PDF
- e-document neutral foundation
- idempotent posting + concurrency tests

Gerçek e-document provider submit A-08 kapanmadan yapılmaz.

## M9 — Satınalma
Dikey alt akışlar ayrı commit/test gate ile:
1. PurchaseOrder
2. GoodsReceipt physical receipt + accepted/pending/rejected quantity split
3. pending quality reclassification
4. SupplierInvoice
5. PurchaseReturn

Zorunlu:
- remaining_to_receive accepted quantity üzerinden
- remaining_to_invoice
- over-acceptance/invoice block
- GoodsReceipt stock IN exactly-once
- pending/rejected unavailable custody
- moving-average cost update
- SupplierInvoice cari effect exactly-once
- SupplierInvoice second stock IN yok

# Wave C — Finance / Instruments / Returns / Reports

## M10 — Tahsilat / Ödeme / Kasa / Banka / Treasury
- immutable `treasury_movements` authority
- dynamic PaymentMethod/PaymentType
- Nakit / Banka / POS / Sanal POS / Çek / Senet / Diğer
- account + treasury atomic effects
- cash/bank movements
- POS Pending/Settled/Reversed/Chargeback
- commission separation
- expense
- virman
- cash accounts / bank accounts secondary screens
- cash count denominations
- Excel/CSV/MT940 import
- statement matching/reconciliation

Cross-currency yalnız A-07 kapanırsa aynı milestone'a alınır. Future payment/open-banking providerları source/evidence üretir; treasury authority olmaz.

## M11 — Çek / Senet
- received/issued cheque
- received/issued promissory note
- portfolio/physical location/holder/history
- front/back files + scanner hooks
- delivery-time cari effect
- bank settlement no second cari effect
- **received instrument endorsement/ciro → supplier payable effect exactly-once**
- dishonored/unpaid/cancel reversal chain
- concurrency

## M12 — Return / RMA Core Foundation
Bu milestone marketplace API connector'ı değildir.

- Return Center
- source line eligibility / returnable quantity
- sales return
- purchase return
- generic ecommerce/RMA source type + external mapping hooks
- physical receipt/inspection/decision
- stock effect
- financial refund/correction
- source lineage

Provider return/status/cargo implementations M17/M18 adapterlarında eklenir. Future warranty/service module M12 Return Core'u source lineage olarak kullanabilir ama aynı şey değildir.

## M13 — Report Platform + Commercial Core Reports
- ready-report catalog infrastructure
- stabil report key registry
- shared filters/KPI/table workspace
- Saved Reports
- Scheduled Reports
- Excel/CSV
- PDF/Print
- runtime authorization
- **yalnız M0–M12 tamamlanmış domainlerin raporları**

Future Üretim/Fason/İthalat/E-Ticaret raporları ilgili milestone ile eklenir. 40 rapor hedefi bu aşamada zorunlu değildir.

### Commercial Functional Gate
M13 sonunda aşağıdaki internal commercial akışlar uçtan uca çalışmalıdır:
- Cari
- Ürün/Stok
- Satış
- Alış
- Kasa/Banka
- Çek/Senet
- İade
- mevcut commercial raporlar

Bu gate **production-ready anlamına gelmez**. Security/backup/recovery/full regression production gate M23'tedir.

# Wave D — Operations

## M14 — Basit Üretim
- recipes
- production order
- material issue + carrying cost
- finished-goods receipt + cost allocation
- fire/missing
- production technical file
- production reports eklenir

No routing/work-center/ECO/OEE/shop-floor platform.

## M15 — Fason
- sent material
- subcontract custody quantity + carrying value
- received finished goods
- fire/missing
- remaining reconciliation
- technical/photos/instructions
- fason reports eklenir

## M16 — İthalat / Konteyner
- import file/shipment
- containers/packages
- product/package/component mapping
- material location
- GoodsReceipt/ImportReceipt stock handoff
- container/general landed-cost analysis
- technical/photo picking/production lists
- subcontract collection lists
- loading/weight/dimension simulator
- import reports eklenir

A-17 landed-cost posting policy cost-posting slice başlamadan kapanır.

# Wave E — External Commerce / Communication

## M17 — E-Ticaret Integration Core + WooCommerce
Entry gate: A-04 public ID gerekiyorsa kapanmış; provider registry contract hazır.

- Channel Center
- Channel Settings/Connection
- provider registry + adapter capability matrix
- encrypted/masked secrets
- connection test
- product/listing mapping
- media/image publish contract
- stock/price desired-state publish
- external order Inbox/idempotency
- customer snapshot
- reservation failure → Sorun/Stok Eksik
- polling/webhook common ingestion path
- generic provider return hooks onto M12 Return Core
- invoice sync foundation
- settlement/payout evidence normalization foundation
- marketplace clearing finance handoff
- provider rate-limit/retry/ambiguous outcome contracts

WooCommerce ilk adapterdır; financial mode `direct_account | clearing_account` config ile çalışabilir.

## M18 — Verified Marketplace Adapter Pack
Her doğrulanmış provider **ayrı atomic alt-milestone/commit serisi** olarak uygulanır. Entry gate: provider registry entry + real contract fixture.

### M18-TY — Trendyol
- credentials
- product/listing/category/attribute
- media capability where supported
- order/package/cancel/return
- stock/price
- questions/invoice operations
- settlement/payout evidence where supported

### M18-HB — Hepsiburada
- merchant/auth
- catalog/listing/media
- stock/price
- orders/shipment/package
- claims/returns/questions
- accounting/settlement evidence where supported

### M18-AMZ — Amazon SP-API
- Türkiye marketplace first, region-aware account model
- SP-API authorization/token lifecycle
- Catalog/Product Type Definitions/Listings
- media capability as provider supports
- inventory/price
- Orders/shipment
- Reports/settlement evidence
- returns/refunds capability
- FBA/FBM distinction

### M18-N11 — n11
- connection
- category/attribute/product/listing
- async task/result
- media capability where supported
- stock/price
- orders/shipment/cancel/return/questions
- settlement evidence if exposed

### M18-PTT — PttAVM
- connection
- product/listing/media where supported
- stock/price
- orders/cargo
- invoice/return capability
- REST-first; legacy SOAP adapter arkasında

### M18-IDF — idefix
- connection
- category/attribute/brand
- product/list/status/media where supported
- stock/price
- orders/shipment/package
- invoice/cancel/return/questions

### M18-ALG — Allesgo
- connection
- product/listing/media where supported
- stock/price
- orders/shipment
- returns/questions
- payment/settlement evidence where supported

### M18 ortak exit gate
Her aktif adapter için:
- registry/docs/version verified
- connection test
- encrypted/masked credential
- capability matrix
- external identity mapping
- order idempotency
- stock stale-retry guard
- media capability deterministic supported/manual behavior
- retry/backoff/rate-limit
- malformed/duplicate fixture tests
- problem center
- sandbox/test smoke where available
- production credential olmadan fake “tam entegre” işareti yok

### Deferred Marketplace Candidates — milestone dışı
Çiçeksepeti, Pazarama, Koçtaş, Teknosa, Temu Türkiye, Boyner.

Gerçek API/seller contract doğrulanınca yeni `M18-*` alt-milestone açılır.

## M19 — B2B / Bayi Sistemi
Entry gate: A-04 public ID/token strategy.

- separate B2B auth guard/context
- activation/deactivation
- password set/reset
- login/logout/session revoke/rate-limit
- B2BUser pre-bound Account
- roles/typed permissions
- Cari Edit B2B access
- readonly Cari Detail B2B
- catalog/search
- stock visibility
- price = sale price - Cari Discount
- cart/order/history
- risk/exposure server-side policy
- invoice/statement
- address permissions

## M20 — Communication / System Integrations / API
`Ayarlar → Entegrasyonlar`:
- SMS
- E-Mail
- WhatsApp
- E-Document
- Scanner Agent

Ayrıca:
- `/api/v1`
- provider adapters
- template/version/preview/test
- Notification → Delivery → ProviderAttempt
- Outbox/retry/backoff

Production provider slice'ları ilgili A-08/A-09/A-10/A-11 kararları kapanmadan “production-ready” sayılmaz.

# Wave F — Product Media / Hardening / Go-Live

## M21 — Product Image Operations
- site/channel destination sets
- main/gallery/order
- copy/move
- optional image editor
- crop/rotate/flip/resize
- provider validation metadata
- security/quarantine lifecycle where enabled

## M22 — Product Installation PDF Builder
- steps
- warnings
- tools
- parts
- images
- A4 preview
- versioned output

Domain-specific builder; generic report/document designer değildir.

## M23 — Security / Backup / Operational Hardening / Production Candidate
Entry gate: A-03 deployment, A-14 storage, A-15 RPO/RTO/backup policy.

- authorization/company isolation review
- B2B auth security review
- file security
- rate limits
- provider retry/kill-switch
- Outbox lease/ambiguous outcome review
- marketplace API deprecation/version review
- marketplace clearing reconciliation review
- backup/restore drill
- Recovery Mode
- log/secret redaction
- performance indexes/query plans
- PostgreSQL search tuning
- report catalog completion toward 40 defined reports
- full Gate A/B/C regression
- required CI/main protection verified

### Production Candidate Gate
M23 sonunda ancak CI green + restore drill + recovery barrier + security review + business reconciliation başarılıysa uygulama **production candidate** sayılır.

## M24 — Migration / Go-Live
Entry gate: A-16 migration depth; A-13 production identity-validation policy kapanmış.

- legacy source inventory/mapping
- stable source identity/idempotent import
- dry-run/rehearsal
- balance/stock/value/cash/bank/instrument reconciliation
- cutover/delta strategy
- **tüm enabled/active external channels** için cursor/watermark/inbox/pause strategy
- provider/channel reconciliation
- active marketplace credential/capability smoke tests
- marketplace clearing opening/settlement reconciliation
- desired stock/price resync
- backup/restore drill
- production smoke
- full V16.3 browser regression

# Post-V1 activation rule
Kargo adapterları, payment gateway, feed/discovery, OCR/AI, mobil depo, ürün family/variant grouping, CRM, servis/garanti, reorder/forecast, dış muhasebe export veya diğer ileri özellikler **V1 milestone numaralarına gizlice eklenmez**.

Yeni feature önce `27_GELECEK_GENISLEME_ALTYAPISI.md` checklist'ini geçer; sonra yeni `M25+` milestone veya ilgili mevcut modülde açık versioned vertical slice olarak planlanır.

## Commit büyüklüğü kuralı
Bir milestone tek commit olmak zorunda değildir. Özellikle Satış, Satınalma, E-Ticaret ve Finans içinde her independently test edilebilir vertical slice ayrı atomic commit olabilir. Ama yarım çalışan aynı use-case main'de bırakılmaz.
