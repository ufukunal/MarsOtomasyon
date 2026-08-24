# 16 — Uygulama Sırası ve Milestone'lar V4

Amaç V16.3 tasarımını gerçek Laravel/PostgreSQL uygulamasına **küçük, çalışan, test edilmiş dikey dilimler** halinde dönüştürmektir. Eski repodaki büyük-bang geliştirme riski tekrar edilmez.

## Genel çıkış kuralı
Her milestone:
`schema → domain use-case/action → transaction/invariant → authorization → V16.3 UI → tests → PostgreSQL CI → audit/observability`.

Bir milestone içindeki görünür butonlar gerçek route/action çalıştırmadan tamamlanmış sayılmaz. Future capability için kullanılmayan interface/table/framework önceden kurulmaz.

# Wave A — Foundation

## M0 — Repository / Laravel / PostgreSQL Foundation
- PHP 8.5 / Laravel 13
- PostgreSQL 18
- Valkey
- Composer lock
- formatter/static analysis/test commands
- `.env.example` ve secret policy
- modular monolith skeleton
- migration smoke test
- health endpoint
- Clock/correlation temel altyapısı
- transactional Outbox skeleton
- PostgreSQL FTS + `pg_trgm`

## M1 — Core / Company / Users / Settings / UI Shell
- auth/session
- Company/Branch context
- user/role/permission
- base currency/timezone
- document numbering
- tax/exchange-rate/posting-period basics
- audit
- Files foundation
- V16.3 sidebar/topbar/workspace tabs/global search/command palette

## M2 — Cari Core
- cari list/create/detail/edit
- Firma / Ticari
- İletişim / Yetkililer
- Sevk / Adres + manuel Ambar/Nakliye
- bank info
- notes/files
- risk limit / cari discount
- B2B access foundation
- `account_transactions`
- balance/statement
- Alacaklı/Borçlu/Bakiye Yok

OpenItem/allocation yok.

# Wave B — Product / Stock / Sales / Purchase

## M3 — Ürün / Katalog
- product/category/unit/barcode
- tek satış + tek alış fiyatı
- readonly detail + separate edit
- supplier relation
- technical info file
- media foundation
- PostgreSQL product search

Lot/serial ve generic price-list yok.

## M4 — Stok / Depo
- warehouse/location
- `stock_movements`
- balances/availability
- reservation
- movement list
- transfer: source issue → yolda → partial/full receipt
- stock count
- barcode Quick Count

Transfer/sayım ekranlarında cari/fiyat/KDV yok.

## M5 — Teklifler
- quote list/create/detail
- revisions
- totals/tax/discount
- approved revision → order
- fixed/versioned PDF
- readonly finalized detail

## M6 — Satış Siparişleri
- order list/create/detail
- product search code/barcode/QR/name
- reservation
- ordered/dispatched/invoiced/remaining
- over-operation guards
- KDV Sıfırla
- partial flows

## M7 — İrsaliye / Sevkiyat
- dispatch list/create/detail
- shipment/address + manual carrier/warehouse suggestion
- previous/current/remaining shipment quantities
- physical stock effect policy exactly-once
- readonly finalized detail

Fiyat/KDV ana odak değildir.

## M8 — Satış Faturaları
- invoice list/create/detail
- direct/order/dispatch lineage
- price/discount/tax totals
- KDV Sıfırla
- order invoiced/remaining progress
- account effect exactly-once
- stock effect only if selected stock policy requires it
- fixed/versioned PDF
- e-document foundation
- idempotent posting + concurrency tests

## M9 — Satınalma
Dikey alt akışlar ayrı commit/test gate ile:
1. PurchaseOrder
2. GoodsReceipt
3. SupplierInvoice
4. PurchaseReturn

Zorunlu:
- remaining_to_receive/invoice
- over-receipt/invoice block
- GoodsReceipt stock IN exactly-once
- SupplierInvoice cari effect exactly-once
- `Uygun / Kontrol Bekliyor / Uygun Değil`

# Wave C — Finance / Instruments / Returns / Reports

## M10 — Tahsilat / Ödeme / Kasa / Banka
- dynamic PaymentMethod/PaymentType
- Nakit / Banka / POS / Sanal POS / Çek / Senet / Diğer
- account + treasury atomic effects
- cash/bank movements
- POS commission separation
- expense
- virman
- cash accounts / bank accounts secondary screens
- cash count denominations
- Excel/CSV/MT940 import
- statement matching/reconciliation

## M11 — Çek / Senet
- received/issued cheque
- received/issued promissory note
- portfolio/physical location/history
- front/back files + scanner hooks
- delivery-time cari effect
- bank settlement no second cari effect
- dishonored/unpaid reversal
- concurrency

## M12 — İadeler / RMA
- Return Center
- sales return
- purchase return
- ecommerce/RMA flow
- eligible quantity cap
- physical stock effect
- financial refund/correction
- source lineage

## M13 — Rapor Merkezi
- ready reports incremental catalog
- 8 categories target
- shared filters/KPI/table workspace
- Saved Reports
- Scheduled Reports
- Excel/CSV
- PDF/Print
- runtime authorization

Hedef yaklaşık 40 hazır rapordur; hepsi tek committe yazılmaz. Generic designer yok.

### Commercial Core Gate
M13 sonunda internal-use production candidate aşağıdaki akışları uçtan uca çalıştırmalıdır:
- Cari
- Ürün/Stok
- Satış
- Alış
- Kasa/Banka
- Çek/Senet
- İade
- temel raporlar

# Wave D — Operations

## M14 — Basit Üretim
- recipes
- production order
- material issue
- finished-goods receipt
- fire/missing
- production technical file
- production report

No routing/work-center/ECO/OEE/shop-floor platform.

## M15 — Fason
- sent material
- custody/location
- received finished goods
- fire/missing
- remaining reconciliation
- technical/photos/instructions

## M16 — İthalat / Konteyner
- import file/shipment
- containers/packages
- product/package/component mapping
- material location
- container/general landed-cost analysis
- technical/photo picking/production lists
- subcontract collection lists
- loading/weight/dimension simulator

# Wave E — External Commerce / Communication

## M17 — E-Ticaret Integration Core + WooCommerce
- Channel Center
- Channel Settings/Connection
- adapter capability matrix foundation
- encrypted/masked secrets
- connection test
- product/listing mapping
- stock/price desired-state publish
- external order Inbox/idempotency
- polling/webhook common ingestion path
- returns/questions/problems
- invoice sync foundation
- provider rate-limit/retry/ambiguous outcome contracts

WooCommerce ilk adapterdır ve ortak contract'ın referans implementasyonlarından biri olur.

## M18 — Verified Marketplace Adapter Pack
Her doğrulanmış provider **ayrı atomic alt-milestone/commit serisi** olarak uygulanır. Bir adapter tamamlanmadan diğerinin provider-specific kodu core'a karıştırılmaz.

### M18-TY — Trendyol
- Supplier ID/API credentials
- product/listing mapping
- category/attribute mapping
- order/package/cancel/return
- stock/price publish
- questions
- invoice operations
- retry/problem center

### M18-HB — Hepsiburada
- merchant/auth connection
- catalog + listing
- stock/price
- orders
- shipment/package
- claims/returns
- seller questions
- accounting/invoice capability where supported
- webhook/polling + rate-limit policy

### M18-AMZ — Amazon SP-API
- Türkiye marketplace first, region-aware account model
- SP-API authorization/token lifecycle
- Catalog Items / Product Type Definitions / Listings mapping
- inventory/price
- Orders
- shipment confirmation
- Reports/settlement evidence
- returns/refunds where provider capability permits
- FBA/FBM capability distinction
- API version/deprecation tracking

### M18-N11 — n11
- app key/secret connection
- category/attribute + product/listing
- async task/result handling
- stock/price
- orders/shipment
- cancellation/returns
- product questions

### M18-PTT — PttAVM
- merchant/API Key/token connection
- product/listing + stock/price according to current API capability
- orders
- cargo operations
- invoice/return capability where supported
- REST-first adapter; legacy SOAP gerekiyorsa adapter arkasında izole

### M18-IDF — idefix
- vendor/API key/secret connection
- category/attribute/brand
- product create/list/status
- stock/price
- orders/shipment/package
- invoice link
- cancellation/returns
- product questions
- order questions

### M18-ALG — Allesgo
- seller/API connection
- product/listing
- stock/price
- orders/shipment
- returns
- product/order questions where supported
- payment/settlement evidence where supported

### M18 ortak çıkış kapısı
Her aktif adapter için:
- connection test
- encrypted/masked credential
- capability matrix
- external identity mapping
- order idempotency
- stock desired-state stale-retry guard
- retry/backoff/rate-limit
- malformed/duplicate fixture tests
- problem center visibility
- sandbox/test account smoke where provider offers it
- production credential olmadan fake "tam entegre" işareti yok

### Deferred Marketplace Candidates — milestone dışı
Şimdilik milestone açılmayacak:
- Çiçeksepeti
- Pazarama
- Koçtaş
- Teknosa
- Temu Türkiye
- Boyner

Bu kanallardan biri ancak güncel resmî API dokümanı veya gerçek seller/partner erişimi doğrulandıktan sonra yeni `M18-*` alt-milestone olarak eklenir.

## M19 — B2B / Bayi Sistemi
- B2B user pre-bound Account
- roles/permissions
- Cari Edit B2B access
- readonly Cari Detail B2B
- catalog/search
- stock visibility
- price = sale price - Cari Discount
- cart/order/history
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

# Wave F — Product Media / Hardening / Go-Live

## M21 — Product Image Operations
- site/channel destination sets
- main/gallery/order
- copy/move
- optional image editor
- crop/rotate/flip/resize
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

## M23 — Security / Backup / Operational Hardening
- authorization/company isolation review
- file security
- rate limits
- provider retry/kill-switch
- Outbox lease/ambiguous outcome review
- marketplace API deprecation/version review
- backup/restore drill
- Recovery Mode
- log/secret redaction
- performance indexes/query plans
- PostgreSQL search tuning
- full Gate A/B/C regression

## M24 — Migration / Go-Live
- legacy source inventory/mapping
- stable source identity/idempotent import
- dry-run/rehearsal
- balance/stock/cash/bank/instrument reconciliation
- cutover/delta strategy
- provider/channel reconciliation
- active marketplace credential/capability smoke tests
- desired stock/price resync
- backup/restore drill
- production smoke
- full V16.3 browser regression

## Commit büyüklüğü kuralı
Bir milestone tek commit olmak zorunda değildir. Özellikle Satış, Satınalma, E-Ticaret ve Finans içinde her independently test edilebilir vertical slice ayrı atomic commit olabilir. Ama yarım çalışan aynı use-case main'de bırakılmaz.
