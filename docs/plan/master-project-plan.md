# MarsOtomasyon — Master Project Plan

Status: DETAILED MASTER PLANNING COMPLETED / IMPLEMENTATION ACTIVE
Owner: Project Owner
Repository: `ufukunal/MarsOtomasyon`
Target branch: `main`
Planning authority: current explicit user instruction > repository reality > accepted decisions > module plans > skill contracts

## 1. Purpose

This document is the end-to-end delivery map for MarsOtomasyon. It defines the order in which the product will be planned, built, verified and released. It is not proof that a phase is implemented. Every phase must produce repository evidence before it can be marked complete.

The project will be delivered in small, auditable work packages. A chat/session must not silently jump across phases. Every completed work package ends with:

1. repository state verification,
2. a concise execution report,
3. updated UNKNOWN/BLOCKED items,
4. fast-test evidence when applicable,
5. Full Test Day backlog updates when applicable,
6. a standalone NEXT PROMPT for the next session.

## 2. Product reference and source hierarchy

The canonical UI/product reference is:

`docs/reference/ui/marsotomasyon_ui_v38_cari_bakiye_sadelestirildi.html`

The V38 HTML is a product/UX reference, not the production codebase. It may contain historical patches and deprecated concepts. A V38 screen or behavior becomes a production requirement only when it is consistent with the current module plan and accepted business decisions.

Source priority:

1. current explicit owner instruction,
2. real repository state and code,
3. `docs/plan/ai-cmd.md`,
4. accepted ADR/decision records,
5. this master plan and current active task,
6. relevant module plan,
7. DB design documents,
8. active skill contracts,
9. verified CI/test evidence,
10. V38 reference where it does not conflict with newer accepted rules,
11. general model knowledge only as labeled analysis, never as a silent business rule.

## 3. Locked architecture baseline

Unless explicitly changed by the project owner:

- Backend: .NET / ASP.NET Core / C#
- Architecture: modular monolith
- API base: `/api/v1`
- Authoritative database: PostgreSQL
- Cache/session/coordination: Valkey
- Background execution: .NET Worker
- Reliable async side-effects: transactional outbox
- Realtime: SignalR
- Frontend: HTML5 + CSS + TypeScript + ES Modules + Vite
- UI system: Mars.UI and Mars-owned components
- Core frontend frameworks forbidden: React, Vue, Angular, Bootstrap, Tailwind, jQuery
- Deployment: Linux + Docker + Docker Compose
- Web/Desktop/Mobile: common client core with thin platform adapters
- Git workflow: main only; no branches, no PR workflow, no force push
- Posted inventory/financial history: append/reversal, not silent mutation
- PostgreSQL is authoritative; projections/cache are rebuildable
- Money and quantity: decimal/NUMERIC, never floating-point accounting truth

## 4. Delivery method

The project follows:

Business purpose
→ workflow
→ effect matrix
→ state machine
→ quantity/financial contracts
→ domain model
→ logical database model
→ migration/schema contract
→ API/event contract
→ UI contract
→ implementation
→ targeted verification
→ test deployment
→ Full Test Day
→ release hardening

SQL-first, UI-first or code-first domain design is forbidden when the business behavior is unresolved.

## 5. Session/work-package model

A work package is the maximum normal unit of one AI session. It must have:

- one clear objective,
- defined repository paths,
- active primary/reviewer roles,
- allowed and forbidden scope,
- source list,
- measurable acceptance criteria,
- quick checks,
- known heavy tests to defer,
- explicit next work package.

No session may mark downstream work complete merely because planning text mentions it.

## 6. Role model

### Project / business manager — `mba-business-manager`
Owns:
- business objective,
- process owner,
- expected benefit,
- risk/cost,
- operational bottleneck,
- KPI/control intent,
- prioritization,
- adoption impact.

Cannot invent technical implementation or accounting rules.

### Software architect — `software-architect`
Owns:
- module boundaries,
- dependency direction,
- transaction boundaries,
- application composition,
- API/event architecture,
- outbox/idempotency architecture,
- client/server boundaries,
- platform abstraction,
- architectural consistency.

Cannot invent domain policy.

### ERP domain specialist — `erp-domain-specialist`
Owns:
- commercial document meaning,
- document/source/target relationships,
- DOC/RES/STOCK/ACCOUNT/CASH-BANK/COST effects,
- partial processing,
- cancel/reversal semantics,
- business state machines.

### Accounting/finance specialist — `accounting-finance-specialist`
Owns:
- debit/credit direction,
- recognition point,
- settlement,
- cash/bank effects,
- tax, currency, rounding,
- financial reversal,
- accounting/treasury invariants.

### Database architect — `database-architect`
Owns:
- normalization,
- entity/relationship model,
- ledger/projection/snapshot separation,
- DB constraints,
- keys,
- indexes,
- concurrency guarantees,
- migration/data integrity strategy.

### Software developer — `software-developer`
Owns:
- implementation of accepted contracts,
- smallest correct code change,
- code quality,
- API/application/domain/infrastructure implementation,
- targeted build/unit checks.

### Software test engineer — `software-test-engineer`
Owns:
- fast verification plan,
- invariant tests,
- negative/state/duplicate/permission cases,
- Full Test Day backlog,
- evidence quality.

### Security specialist — `security-specialist`
Owns:
- authentication boundary,
- authorization,
- company/tenant isolation,
- secret policy,
- file/webhook security,
- OWASP controls,
- audit/security review.

### System/DevOps specialist — `system-devops-specialist`
Owns:
- Linux/Docker topology,
- deployment,
- readiness/health,
- migrations in deployment,
- backups/restores,
- observability infrastructure,
- rollback,
- capacity.

### UX/UI + Web + Graphic roles
`ux-ui-specialist`, `web-design-specialist`, `graphic-design-specialist` own:
- ERP task flow,
- keyboard/touch behavior,
- Mars.UI component contracts,
- semantic HTML/accessibility,
- responsive behavior,
- design tokens and visual consistency,
- V38 visual/product continuity.

### Warehouse / Production / Commerce / Statistics specialists
Activated only when their domain is affected and own their respective operational contracts.

## 7. Phase map

### P0 — Governance and execution protocol
Goal: make every future session reproducible and auditable.

Deliverables:
- AI command protocol
- skill router
- role matrix
- planning standard
- task/state/handoff mechanism
- NEXT PROMPT protocol
- decision/ADR area
- main-only Git policy

Exit:
- every session can resume from repo without chat memory.

### P1 — Foundation / Framework
Goal: define and then implement the reusable technical framework before domain modules.

Planning deliverables:
- solution/project topology
- dependency rules
- module template
- command/query pipeline
- transaction/unit-of-work contract
- error/result model
- validation contract
- actor/company/branch context
- permissions primitive
- public/internal identity convention
- audit primitive
- idempotency primitive
- transactional outbox
- worker execution contract
- realtime contract
- files abstraction
- notifications abstraction
- API conventions
- OpenAPI decision gate
- DB/migration ownership model
- PostgreSQL role model
- Valkey usage contract
- configuration/secrets contract
- observability contract
- Mars.UI/client architecture
- Mars.Grid/Mars.Lookup/Mars.Dialog/Mars.Tabs contracts
- platform adapter contract
- Docker/Compose layout
- health/readiness contract
- test architecture
- deployment/test-server flow

Implementation begins only after the framework contract is accepted.

Exit:
- framework can host one thin vertical slice without domain-specific hacks.

### P2 — Core commercial domain planning
Order:
1. Sales
2. Party/Customer/Supplier
3. Product/Inventory Master
4. Purchasing
5. Warehouse
6. Finance/Treasury
7. Checks/Promissory Notes
8. Returns/RMA

Exit:
- physical and financial recognition points are explicit,
- partial/cancel/reversal behavior is explicit,
- no double-stock/double-accounting ambiguity remains,
- source/target document relations are explicit.

### P3 — Logical database model
Begins only after P2 workflows are sufficiently frozen.

Deliverables:
- domain dictionary v2
- entity catalog
- relationship model
- module/schema ownership
- commercial document strategy
- inventory/account/cash/bank ledger models
- reservation model
- historical snapshots
- projections/read models
- outbox/idempotency/audit tables
- key/public-id strategy
- constraints
- indexes from access patterns
- concurrency rules
- migration conventions

Exit:
- every authoritative table traces to accepted workflow requirements.

### P4 — Foundation implementation
Build the framework defined in P1.

Expected solution target, subject to accepted framework contract:

```
Mars.sln
src/
  Mars.Api/
  Mars.Application/
  Mars.Domain/
  Mars.Infrastructure/
  Mars.Worker/
  Mars.Contracts/
  Mars.Device/
  Mars.Web/
tests/
  ...
```

Exact project/package split is frozen by the Framework Plan before creation.

Exit:
- build succeeds,
- test environment deploys a thin framework slice,
- health/readiness works,
- DB migration path works,
- outbox/idempotency/audit primitives have targeted tests.

### P5 — Core application implementation
Dependency order:
1. Parties
2. Products
3. Inventory
4. Sales
5. Purchasing
6. Warehouse
7. Finance/Treasury
8. Returns
9. Checks/Notes

Each module is delivered vertical-slice style:
plan verification → DB migration → domain/application/API → UI → targeted tests → test deploy/smoke.

### P6 — Operations
Modules:
- Quality
- Production
- Subcontracting
- Import/Container
- MRP
- Capacity Planning
- Maintenance
- Service/Warranty
- Sample/Consignment
- Contracts/Periodic Operations
- Fixed Assets
- Carrier Performance

### P7 — Commerce and external channels
- Commerce Core
- B2B Portal
- Architect Portal
- WooCommerce
- Trendyol
- Hepsiburada
- N11
- ÇiçekSepeti
- Idefix
- catalog mapping
- channel content overrides
- channel pricing
- sellable stock calculation
- order ingest
- returns
- questions/messages
- webhook + reconciliation
- provider observability/error center

Provider capabilities must be verified against current provider documentation at implementation time.

### P8 — Communications, files and device layer
- email
- SMS
- WhatsApp
- push
- notification center
- templates
- provider routing
- file storage/download policy
- scanner
- barcode
- camera
- printer routing
- ESC/POS
- ZPL/TSPL/RAW
- device registration
- offline sync contracts

### P9 — Reporting, BI, management and control
- dashboard
- KPI dictionary
- Mars.Reporting
- report designer
- saved reports/views
- profitability
- operational KPIs
- CRM
- SoD/control center
- scheduled reporting

Every KPI requires formula/grain/filter/currency/time/status rules.

### P10 — Cross-platform shells
- web production shell
- desktop shell
- mobile shell
- shared client core
- platform adapters
- offline strategy where accepted
- device integration

Exact desktop/mobile technology remains a decision gate until formally selected.

### P11 — Full Test Day
Runs only when explicitly started.

Includes:
- full unit/regression
- PostgreSQL integration
- browser E2E
- Web/Desktop/Mobile
- accounting/ledger invariants
- concurrency/idempotency
- backup/restore
- security
- provider sandbox/reconciliation
- performance/load
- migration upgrade/rollback rehearsal
- print/device flows

### P12 — Release hardening
- production topology verification
- secret rotation/storage
- migration rehearsal
- backup/restore evidence
- rollback rehearsal
- monitoring/alerting
- operational runbooks
- capacity baseline
- release checklist
- owner acceptance

## 8. Module planning order after Framework

The full planning sequence is:

01 Foundation/Framework
02 Sales
03 Parties/Cariler
04 Product/Inventory Master
05 Purchasing
06 Warehouse
07 Finance/Treasury
08 Checks/Notes
09 Returns/RMA
10 Quality
11 Production
12 Subcontracting
13 Import/Container
14 Commerce/B2B/API
15 Communications/Files
16 Reporting/BI
17 Settings/System
18 MRP
19 Capacity
20 Maintenance
21 Service/Warranty
22 Sample/Consignment
23 Contracts/Periodic
24 Fixed Assets
25 CRM
26 Carrier Performance
27 SoD
28 Device Layer
29 Full Test Day
30 Release Hardening

Directory numbers already present in the repository remain canonical where they differ from this conceptual sequence.

## 9. V38 migration rule

For every module, V38 screens are classified as:

- KEEP — product/UX behavior remains valid
- ADAPT — visual/workflow idea remains but domain contract changes
- MERGE — duplicate screens collapse into one canonical workflow
- REMOVE — deprecated/obsolete concept
- BLOCKED — requires owner/domain decision

Production code is never copied from V38 patch chains without re-deriving it into the accepted architecture.

## 10. Test policy

Normal session:
- build
- targeted unit/invariant
- small smoke when needed
- migration/contract quick check

Never by default:
- full regression
- broad browser E2E
- load/performance
- destructive restore
- full security sweep
- full provider matrix

Those are recorded for Full Test Day.

## 11. Decision gates that must not be guessed

Current examples:
- exact .NET SDK/LTS version
- exact ORM/data-access package
- exact auth/identity provider
- exact desktop shell
- exact mobile shell
- exact object/file storage
- exact OpenAPI tooling
- exact structured logging/metrics stack
- exact job scheduling library if a scheduler is needed beyond Worker
- production reverse proxy/tunnel details
- production secret store

Each becomes an ADR or explicit owner decision before implementation depends on it.

## 12. Definition of overall DONE

MarsOtomasyon is release-ready only when:
- required domain workflows are implemented,
- ledger and snapshot invariants are verified,
- permissions and company/branch scope are enforced server-side,
- production migrations are rehearsed,
- backup/restore is proven,
- test and production deployment are reproducible,
- Full Test Day blockers are closed or explicitly accepted,
- operational monitoring/runbooks exist,
- V38-required user journeys are mapped to production implementations,
- owner acceptance is recorded.

## 13. Current execution order

Current owner instruction resets immediate priority to:

1. finalize Foundation/Framework contract,
2. formalize the session report/NEXT PROMPT protocol,
3. then return to end-to-end domain planning beginning with Sales,
4. continue through the master sequence without skipping dependencies.

The previously active Sales planning task is paused, not completed or discarded.


## 14. Planning completion

Canonical portfolio completion:

`docs/plan/project-plan-completion.md`

Master portfolio planning coverage:

- 30 / 30 = 100%

Master detailed planning coverage:

- 30 / 30 = 100%

Foundation/core-commercial/PLAN-010 plans remain authoritative. Later-module detailed plans are frozen in their canonical module directories, with explicit UNKNOWN/implementation gates where V38/repository sources do not define policy.

When a module becomes current, only a short repository reconciliation/readiness pass is required; global or module planning must not be repeated.

This completion does not mark future implementation, Full Test Day or Release Hardening execution as done.
