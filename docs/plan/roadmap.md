# MarsOtomasyon Project Roadmap

## Planning principle
The roadmap is ordered by dependency and business truth, not by menu order. Each phase must reach its own acceptance criteria before downstream implementation relies on it.

## Phase P0 — Governance and planning backbone
**Status:** ACTIVE

Deliverables:
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

Exit criteria:
- a new session can resume from repo only
- no undocumented next action is needed
- main-only workflow is explicit and technically enforced

## Phase P1 — Foundation contracts
Target: `docs/plan/01-foundation/`

Define:
- modular-monolith boundaries
- solution/package layout
- command/query conventions
- transactions
- outbox
- idempotency
- audit
- numbering
- permissions
- tenancy/company/branch scope
- error model
- file/document abstractions
- notification foundation
- API versioning
- client platform abstraction
- observability
- configuration/secrets
- migration policy

Exit criteria:
- implementation can start without inventing shared infrastructure rules

## Phase P2 — Core commercial workflows
Order:
1. `03-cariler`
2. `04-urun-stok`
3. `05-satis`
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
- deployment reproducibility
- backup/restore evidence
- migration rehearsal
- rollback rehearsal
- observability
- operational runbooks
- release checklist

## Current next planning action
After P0/P1 planning backbone, detail the Sales chain first because it anchors reservation, stock, receivable, partial processing and downstream document linking.
