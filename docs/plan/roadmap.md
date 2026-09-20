# MarsOtomasyon Project Roadmap

## Planning principle
The roadmap is ordered by dependency and business truth, not by menu order. Each phase must reach its acceptance criteria before downstream implementation relies on it.

## Phase P0 — Governance and planning backbone
**Status:** COMPLETED

Delivered:
- AI operating protocol
- detailed skill system
- autocomplete/NEXT PROMPT protocol
- project-state
- active-task
- roadmap
- handoff
- task tracking
- decision log structure
- planning standard

## Phase P1 — Foundation contracts
**Status:** PLANNING BASELINE ESTABLISHED

Target: `docs/plan/01-foundation/`

Baseline now defines:
- modular-monolith boundaries
- command/query conventions
- transactions
- outbox
- idempotency
- audit
- numbering
- permissions
- company/branch scope
- error model
- files/notifications abstractions
- API/client/cache/observability/migration policies

Implementation-specific unresolved choices remain explicitly UNKNOWN and are not to be invented.

## Phase P2 — Core commercial workflows
**Status:** ACTIVE

Planning order:
1. `05-satis` — active first because it anchors reservation, stock-out, receivable and partial document linking
2. `03-cariler`
3. `04-urun-stok`
4. `07-satinalma`
5. `06-ambar-depo`
6. `09-finans-kasa-banka`
7. `10-cek-senet`
8. `11-iadeler-rma`

Critical end-to-end chains:
- Quote → Sales Order → Dispatch → Invoice → Collection → Return
- Purchase Order → Goods Receipt → Supplier Invoice → Payment → Purchase Return
- Transfer → Transit → Receipt
- Count → Difference Approval → Adjustment

Exit criteria:
- form/effect matrices complete
- partial/cancel/reversal behavior explicit
- ledger ownership explicit
- no double stock/accounting posting ambiguity

## Phase P3 — Database logical model and schema
**Status:** NOT STARTED

Target: `docs/db/`

Produce:
- domain dictionary
- entity model
- relationships
- normalization decisions
- ledgers
- document engine
- snapshots
- projections
- migration conventions
- PostgreSQL schema plan

Exit criteria:
- every critical table derives from an accepted workflow
- constraints/invariants documented
- source-of-truth vs projection explicit

## Phase P4 — Core application implementation
**Status:** NOT STARTED

Build in dependency order:
- Foundation
- Accounts/Parties
- Products
- Inventory
- Sales
- Purchasing
- Finance/Treasury
- Returns

Normal development tests stay targeted. Heavy tests remain deferred.

## Phase P5 — Operations
**Status:** NOT STARTED

Modules:
- `08-kalite`
- `12-uretim`
- `13-fason`
- `14-ithalat-konteyner`
- `19-mrp-planlama`
- `20-kapasite-planlama`
- `21-bakim`
- `22-servis-garanti`
- `23-numune-konsinye`
- `24-sozlesmeler-periyodik`
- `25-sabit-kiymet`
- `27-tasiyici-performansi`

## Phase P6 — Commerce and external channels
**Status:** NOT STARTED

Target: `15-e-ticaret-b2b-api`

Subdomains:
- Commerce Core
- B2B Portal
- Architect Portal
- WooCommerce
- Trendyol
- Hepsiburada
- N11
- ÇiçekSepeti
- Idefix
- pricing
- inventory sync
- order sync
- returns
- integration monitoring

Provider capabilities must be verified against current provider documentation before implementation.

## Phase P7 — Communications and device layer
**Status:** NOT STARTED

Targets:
- `16-iletisim-dosyalar`
- `29-device-layer`

Define:
- Email
- SMS
- WhatsApp
- Push
- notification center
- templates
- provider routing
- webhooks
- device registrations
- scanner
- barcode/label
- printer routing
- ESC/POS
- ZPL/TSPL/RAW

## Phase P8 — Reporting, management and control
**Status:** NOT STARTED

Targets:
- `02-ana-sayfa`
- `17-raporlar-bi`
- `18-ayarlar-sistem`
- `26-crm`
- `28-sod`

Includes:
- dashboards
- KPI contracts
- Mars.Reporting
- report designer
- charts
- permissions/settings
- CRM
- segregation of duties

## Phase P9 — Full Test Day
**Status:** DEFERRED BY POLICY

Run only when explicitly started.

Includes:
- full unit/regression
- PostgreSQL integration
- browser E2E
- desktop/mobile
- B2B/Architect/Marketplace
- communications
- security
- backup/restore
- concurrency
- performance/load
- accounting/ledger invariants

## Phase P10 — Release hardening
**Status:** NOT STARTED

- deployment reproducibility
- backup/restore evidence
- migration rehearsal
- rollback rehearsal
- observability
- operational runbooks
- release checklist

## Current active planning task
`PLAN-002 — Sales workflow contract`
