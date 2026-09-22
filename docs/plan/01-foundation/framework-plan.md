# MarsOtomasyon — Foundation / Framework Plan

Status: ACTIVE
Work package: FRAMEWORK-001
Purpose: freeze the technical framework contract before domain implementation

## 1. Objective

Build a framework that can support the full MarsOtomasyon ERP without reproducing the V38 single-file patch architecture and without forcing domain-specific behavior into generic infrastructure.

Framework owns reusable technical capabilities. It must not own Sales, Inventory, Finance, Production, Commerce or other domain policy.

## 2. Active roles

Primary:
- software-architect — architecture, boundaries, contracts, dependency direction
- software-developer — implementability, code organization, minimal reusable abstractions

Reviewers:
- database-architect — persistence boundaries, migrations, DB integrity
- security-specialist — trust boundaries, auth/authorization/secrets
- system-devops-specialist — deployment, health, Docker, backup/restore compatibility
- software-test-engineer — testability and evidence model
- ux-ui-specialist — shared interaction primitives
- web-design-specialist — client/component architecture and performance
- graphic-design-specialist — Mars visual-system consistency where design tokens are involved
- erp-domain-specialist — veto only when framework accidentally embeds/violates ERP semantics

## 3. Framework non-goals

Framework must not contain:
- Sales pricing policy
- reservation allocation policy
- stock posting policy
- customer balance logic
- invoice settlement logic
- purchasing match rules
- manufacturing/MRP logic
- marketplace provider business rules
- report KPI formulas
- screen-specific business behavior

## 4. Runtime topology

Logical runtime:

```
Browser / Desktop shell / Mobile shell
                |
                v
             Mars.Web
                |
            HTTPS/API
                |
                v
             Mars.Api
                |
       Mars.Application
                |
          Mars.Domain
                |
        Mars.Infrastructure
          /           \
 PostgreSQL          Valkey
                |
             Outbox
                |
                v
            Mars.Worker
                |
 external providers / notifications / integrations
```

SignalR is used only where realtime delivery has a concrete requirement. Polling must not be introduced where an explicit event/refresh model is sufficient.

## 5. Solution/project topology

Planning target:

```
Mars.sln
src/
├── Mars.Domain
├── Mars.Application
├── Mars.Contracts
├── Mars.Infrastructure
├── Mars.Api
├── Mars.Worker
├── Mars.Device
└── Mars.Web

tests/
├── Mars.Domain.Tests
├── Mars.Application.Tests
├── Mars.Infrastructure.Tests
├── Mars.Api.Tests
└── Mars.Web.Tests
```

This is a framework target. Exact test-project splitting may be reduced if it creates empty/meaningless projects.

### Dependency direction

Allowed conceptual direction:

```
Mars.Domain        -> no infrastructure dependency
Mars.Application   -> Mars.Domain, Mars.Contracts
Mars.Infrastructure-> Mars.Domain, Mars.Application contracts
Mars.Api           -> Mars.Application, Mars.Contracts, composition root
Mars.Worker        -> Mars.Application, Infrastructure composition
Mars.Device        -> device/platform contracts only
Mars.Web           -> public API contracts/client code, never server internals
```

No circular project references.

## 6. Modular monolith structure

Business modules live behind explicit boundaries. Candidate conceptual modules include:

- Foundation
- Parties
- Products
- Inventory
- Sales
- Purchasing
- Warehouse
- Finance
- Returns
- ChecksNotes
- Quality
- Production
- Subcontracting
- Import
- Commerce
- Communications
- Reporting
- Settings
- CRM
- Device

The exact namespace/project packing is decided during implementation to avoid creating dozens of empty assemblies. Module ownership matters more than assembly count.

Each module owns:
- domain entities/value objects
- invariants
- commands/queries
- persistence mapping
- application services/handlers
- internal domain events
- permissions
- module API surface

Cross-module access uses explicit application/domain contracts; modules do not directly manipulate another module's internal tables/entities as a convenience.

## 7. Command framework

Every state-changing command contract carries, where relevant:

- actor identity
- company scope
- optional branch scope
- target public identifier
- expected version/state
- idempotency key
- command timestamp/correlation id
- validated input
- authorization requirement

Command execution order:

1. request parsing
2. authentication
3. correlation/context creation
4. authorization and scope validation
5. input validation
6. load authoritative state
7. domain invariant/state transition
8. transaction start/participation
9. authoritative DB changes
10. audit record where required
11. outbox records in same transaction
12. commit
13. response mapping

External network calls do not determine whether the DB transaction commits.

## 8. Query framework

Queries:
- cannot mutate business state,
- always apply company/tenant/branch scope as required,
- use paging/filter/sort for unbounded lists,
- may use read projections,
- may use Valkey only as non-authoritative cache,
- must define stale/cache behavior where caching is used,
- never expose internal BIGINT IDs unless there is an explicit internal-only contract.

## 9. Transaction contract

- One user/business action has one explicit transaction boundary.
- Posted inventory/financial effects are atomic with their authoritative source change when the workflow requires it.
- Outbox insert is atomic with business state.
- Provider calls are outside the DB atomic transaction.
- No default distributed transaction.
- Retry is permitted only with idempotency/duplicate safety.
- Transaction ownership belongs in application/infrastructure orchestration, not UI.

## 10. Result/error model

Common categories:
- Validation
- Authentication
- Authorization
- NotFound
- Conflict / InvalidState
- Concurrency
- BusinessRule
- RateLimit where applicable
- Infrastructure
- ProviderFailure

API mapping must be deterministic. Internal exception details are not leaked to clients.

Unknown exact envelope/RFC tooling is a decision gate; semantics are locked, library choice is not.

## 11. Validation contract

Validation layers:
- transport/request shape
- application preconditions
- domain invariants
- database constraints

Client validation improves UX but never replaces server enforcement.

Validation messages carry stable codes where useful so web/desktop/mobile clients can handle them consistently.

## 12. Actor, company and branch context

Foundation provides immutable request/job context:
- ActorId/public identity
- CompanyId
- optional BranchId
- CorrelationId
- locale/timezone where accepted
- device/session metadata where relevant

No module may silently use a global company or infer branch from UI state without server-side validation.

Warehouse scope belongs to Inventory/Warehouse rules, not generic Foundation.

## 13. Authorization and permission primitive

Permission naming pattern:

`<module>.<resource>.<action>`

Examples:
- `sales.invoice.create`
- `sales.invoice.post`
- `inventory.count.approve`
- `finance.payment.approve`

Framework provides:
- permission evaluation interface,
- company/branch scope hooks,
- endpoint/command enforcement hooks,
- policy registration convention.

Domain modules define actual permissions.

Exact authentication/identity provider remains UNKNOWN until a deliberate decision.

## 14. Identity contract

Planning default:
- internal DB key: BIGINT where suitable
- external/public API identity: UUID
- provider/external IDs: separate mapping

This is reviewed per entity and must not be blindly duplicated everywhere.

## 15. Concurrency framework

Foundation supports:
- optimistic concurrency token/version where applicable,
- deterministic conflict response,
- DB unique constraints for duplicate-sensitive operations,
- explicit locking only when justified by domain risk.

Oversell, duplicate posting, duplicate callback and reservation races are domain cases built on these primitives.

## 16. Idempotency framework

Required candidates:
- document posting
- external order ingest
- webhooks
- payment callbacks
- worker event consumption
- mobile/offline retries

Properties:
- durable PostgreSQL-backed guarantee,
- scope/key uniqueness,
- request fingerprint where appropriate,
- result/status retention,
- concurrent duplicate behavior,
- expiration/cleanup policy decided by use case.

Valkey alone cannot provide the authoritative idempotency guarantee.

## 17. Audit framework

Audit captures as applicable:
- actor
- time
- action
- module
- entity/document public reference
- company/branch
- reason
- correlation id
- selected before/after values only when safe and useful

Audit is not a financial/inventory ledger and never replaces authoritative business history.

## 18. Transactional outbox

Conceptual fields:
- internal id
- event/public id
- event type
- aggregate/module reference
- payload schema version
- payload
- created_at
- available_at
- processed_at
- attempt_count
- last_error

Processing:
- worker claims available records safely,
- handler is idempotent,
- retry classification is explicit,
- poison/failure state becomes observable,
- successful processing is recorded,
- provider-specific adapters remain outside domain.

Exact SQL shape waits for DB logical design.

## 19. Background Worker framework

Worker responsibilities:
- outbox delivery
- integration synchronization
- notification delivery
- scheduled reconciliation when approved
- retry/backoff orchestration

Worker requirements:
- graceful shutdown
- cancellation tokens
- correlation/event id propagation
- bounded retry
- dead/failure visibility
- company/provider context
- duplicate-safe consumption

Exact scheduling library remains UNKNOWN unless simple Worker timers are insufficient.

## 20. Realtime / SignalR

SignalR is an infrastructure capability, not a default screen behavior.

Candidate uses:
- notification center
- long-running operation progress
- provider sync status
- selected operational dashboards

Must define:
- authorization
- company/user groups
- reconnection behavior
- message versioning
- fallback refresh behavior

## 21. File abstraction

Foundation contract:
- file id/public id
- storage key
- original filename metadata
- MIME
- size
- checksum when useful
- owning module/entity reference
- uploader/created_at
- authorization hook

Security:
- random storage key
- path traversal impossible
- download authorization
- size/type validation
- optional malware scanning by risk

Exact object/file storage backend remains UNKNOWN.

## 22. Notification abstraction

Flow:

```
Domain/Application event
→ Outbox
→ Worker
→ Communication Orchestrator
→ Provider Adapter
```

Core modules never directly call SMS/email/WhatsApp providers.

Framework defines:
- channel-neutral notification request
- template reference
- recipient abstraction
- correlation/business reference
- send state/error contract

Provider-specific capabilities are module/adapter concerns.

## 23. API framework

Base: `/api/v1`

Standards:
- HTTPS
- explicit request/response DTOs
- public UUIDs
- deterministic error model
- pagination/filter/sort conventions
- correlation id
- idempotency key header/field where required
- server-side authorization
- API contract versioning rules
- OpenAPI generation

Exact OpenAPI package/tool remains a decision gate.

## 24. API list/query conventions

Every unbounded list endpoint defines:
- page/cursor strategy
- page size limits
- allowed filters
- allowed sorts
- stable sort/tie-breaker
- search semantics
- total count policy
- empty result behavior

Large lookup data must not be sent as full dropdown payloads.

## 25. Database framework boundary

PostgreSQL is authoritative.

Framework supports:
- connection management
- transaction abstraction
- migrations
- module mapping conventions
- read projections
- idempotency/outbox/audit persistence

Framework forbids:
- authoritative mutable customer balance column
- authoritative mutable product stock column
- silent update/delete of posted ledger history
- schema changes outside migrations

Default ORM/data-access baseline is **EF Core 10 + Npgsql**, accepted by `docs/plan/decisions/ADR-0002-ef-core-npgsql-baseline.md`. Targeted raw Npgsql/SQL is permitted only for explicitly justified specialized cases and must not become a second default persistence architecture.

## 26. PostgreSQL role/ownership model

Current test environment has:
- `mars_master`: administrative superuser for owner/administration only
- `mars_app`: restricted application runtime role

Framework target must additionally distinguish migration/schema ownership from runtime privileges if required.

Policy:
- application does not run as superuser,
- production credentials are outside repository,
- least privilege,
- migration authority is explicit,
- runtime role receives only required object privileges.

Exact production role names/secret-store wiring are resolved before production deployment.

## 27. Migration framework

Every schema change:
- is represented by migration,
- identifies module ownership,
- evaluates lock risk,
- evaluates backfill,
- marks destructive changes,
- defines deploy ordering,
- has forward-fix/rollback strategy,
- is tested against test PostgreSQL when implementation exists.

Application cannot report healthy when a required incompatible migration is missing/failed.

## 28. Valkey contract

Allowed:
- cache
- session
- justified distributed locks
- transient coordination
- rate-limit/support state where accepted

Forbidden:
- authoritative stock
- authoritative balance
- only copy of idempotency truth
- only copy of accounting state

System must recover correctly after cache flush.

## 29. Configuration and secrets

Environments:
- Development
- Test
- Production

Requirements:
- typed configuration
- startup validation
- secrets outside source control for production
- test-only exception is isolated to the explicit owner-approved test credential policy
- secrets never logged
- provider credentials isolated by provider/account
- rotation possible
- fail fast on missing critical settings

## 30. Observability contract

Every server request/background job should support:
- timestamp
- level
- service/module
- correlation id
- request/event id
- actor/company when safe
- duration
- outcome/error code

Metrics baseline:
- API latency/error rate
- worker/outbox age
- outbox failures
- DB health/connections
- Valkey health
- disk
- CPU/memory
- backup age
- provider sync failures

Exact logging/metrics stack remains UNKNOWN.

## 31. Health/readiness

Separate concepts:
- liveness: process can run
- readiness: can safely serve intended work
- dependency diagnostics: PostgreSQL/Valkey/provider state

Migration incompatibility can make API not-ready.

Provider outage must not necessarily make the whole ERP not-ready; dependency criticality is explicit.

## 32. Mars.Web / frontend framework

Technology:
- semantic HTML
- CSS
- TypeScript
- ES Modules
- Vite
- no React/Vue/Angular/Bootstrap/Tailwind/jQuery

Architecture:
- application shell
- router/navigation
- API client
- auth/session adapter
- shared state only where truly global
- module entry points
- Mars.UI component library
- platform/device adapters
- print styles
- localization/time/currency formatting contracts

Business truth does not live in DOM or localStorage.

## 33. V38 UI continuity

V38 remains the canonical visual/product reference.

Framework should preserve its useful product character:
- dense ERP information layout
- white surfaces
- controlled blue accent
- square/hard-edge controls
- clear grid lines
- work tabs where useful
- list/filter/detail workflow
- keyboard-efficient operation
- professional document density

Framework must not preserve:
- chained render monkey patches
- repeated global event handlers
- giant global state
- localStorage as business persistence
- inline business rules
- version-by-version override layers

## 34. Mars.UI component foundation

Initial shared components/contracts:

### Mars.Button
- primary/secondary/danger/quiet
- disabled/loading
- keyboard/accessibility

### Mars.Field
- label
- required
- error/help
- readonly/disabled
- input type adapters

### Mars.Lookup
- F2
- async search
- keyboard navigation
- server paging/filter
- selected entity identity/display

### Mars.Grid
- server paging/filter/sort
- column definitions
- keyboard navigation
- selection
- totals
- saved-view hook
- virtualisation decision based on measured need
- no full unbounded DOM rendering

### Mars.Dialog
- focus trap
- Escape policy
- primary action
- validation/error
- accessible naming

### Mars.Tabs / WorkTabs
- deterministic active state
- close behavior
- unsaved-change policy hook

### Mars.Status
- text/icon + color
- never color-only

### Mars.Toast / Notification UI
- severity
- deduplication
- timeout/action policy

### Mars.Document
- header
- lines/grid
- totals
- source/target references
- state display
- action slot
- print contract

Domain behavior remains supplied by the module.

## 35. Frontend state contract

- server is authoritative for business state,
- component-local UI state stays local,
- cross-page session state is explicit,
- global mutable singleton state is minimized,
- no business state derived from arbitrary DOM attributes,
- optimistic UI only when rollback/conflict behavior is explicit,
- unsaved draft behavior defined per workflow,
- API errors preserve user input where safe.

## 36. Keyboard/accessibility baseline

Foundation UI defines:
- visible focus
- logical tab order
- Escape behavior
- Enter behavior per component
- F2 lookup
- optional Ctrl+S for draft save where module permits
- grid keyboard navigation
- dialog focus lifecycle
- accessible labels/names
- status not color-only

## 37. Responsive/platform contract

Desktop:
- dense grids
- multi-column forms
- keyboard-heavy operation

Mobile:
- task-focused flows
- touch targets
- scan-first when relevant
- reduced columns
- explicit offline/sync status when offline exists

Mobile is not a scaled-down desktop grid.

## 38. Mars.Device abstraction

Client core may request:
- barcode scan
- camera
- file picker
- print
- notifications
- secure credential/session storage where needed

Platform shells implement adapters. Domain/UI modules depend on capability interfaces, not platform-specific APIs.

Exact Desktop/Mobile shell technology remains UNKNOWN.

## 39. Docker/Compose framework

Target service families:
- web
- api
- worker
- PostgreSQL
- Valkey
- optional reverse proxy/tunnel/file service as separately accepted

Requirements:
- immutable app images
- health checks
- restart policy
- non-root where practical
- persistent DB/file volumes
- environment configuration
- secrets not baked into image
- deterministic image/version identification

Kubernetes is not a default requirement.

## 40. Test environment flow

Verified topology:
GitHub private repo
→ self-hosted runner on separate `mars-ci`
→ network/Tailscale
→ separate test server
→ Docker application services + PostgreSQL + Valkey

Normal future deployment:
main
→ checkout
→ build
→ targeted tests
→ migration safety/check
→ deploy test
→ readiness
→ small smoke
→ evidence

A successful runner-local check is not proof of remote test-server health.

## 41. Testing architecture

### Fast tests
- pure domain invariants
- application handler/precondition tests
- validation/error mapping
- idempotency targeted tests
- migration/contract checks
- small API smoke
- small UI/component smoke when justified

### Full Test Day
- full regression
- PostgreSQL integration suite
- browser E2E
- Web/Desktop/Mobile
- full permission matrix
- concurrency
- backup/restore
- performance/load
- provider sandbox
- accounting/ledger invariants
- security regression

## 42. Framework implementation sequence

After this plan is accepted:

FW-IMP-001 — repository solution skeleton
- create solution/projects/directories
- lock dependency direction
- minimal build

FW-IMP-002 — configuration/context/error primitives
- config validation
- correlation
- actor/company context interfaces
- result/error contracts

FW-IMP-003 — persistence/migration decision and baseline
- decide ORM/data-access
- create PostgreSQL connection/migration mechanism
- no domain schema yet beyond framework-owned structures

FW-IMP-004 — audit/idempotency/outbox foundations
- logical schema derived from DB plan
- application interfaces
- worker skeleton
- targeted tests

FW-IMP-005 — API foundation
- middleware/pipeline
- auth integration after provider decision
- error mapping
- OpenAPI after tooling decision
- health/readiness

FW-IMP-006 — Mars.Web + Mars.UI foundation
- Vite/TypeScript
- shell/router/api client
- design tokens
- Button/Field/Dialog/Tabs/Lookup/Grid baseline

FW-IMP-007 — Docker/test deployment baseline
- images/compose
- migration/startup policy
- deploy to test
- smoke evidence

FW-IMP-008 — thin vertical framework proof
- a non-domain or deliberately minimal feature proves request → application → DB → API → UI → audit/outbox pattern without inventing ERP rules.

## 43. Framework acceptance criteria

Framework planning is accepted when:
- project/dependency topology is explicit,
- module ownership rules are explicit,
- command/query/transaction contracts are explicit,
- error/validation/context/permission primitives are explicit,
- DB/Valkey authority boundaries are explicit,
- idempotency/audit/outbox contracts are explicit,
- API conventions are explicit,
- file/notification/realtime abstractions are explicit,
- Mars.Web/Mars.UI architecture is explicit,
- V38 preservation vs technical-debt rules are explicit,
- Docker/test deployment model is explicit,
- test policy is explicit,
- unresolved technology choices are listed as decision gates instead of guessed.

Framework implementation is accepted only after real code/build/deploy evidence exists; this plan alone does not satisfy implementation.

## 44. Decision gates before implementation

BLOCK implementation at the relevant step until explicitly resolved:

- .NET SDK/runtime version — RESOLVED: .NET 10 LTS (`ADR-0001`)
- ORM/data-access library — RESOLVED: EF Core 10 + Npgsql (`ADR-0002`)
- auth/identity provider
- OpenAPI tooling
- logging/metrics stack
- object/file storage backend
- Desktop shell technology
- Mobile shell technology
- optional scheduling library
- production secret store
- production reverse proxy/tunnel details

These do not block documentation of domain workflows unless that workflow directly depends on the missing choice.


## P4 readiness status

The current implementation-readiness classification is maintained in:
`docs/plan/01-foundation/p4-readiness-decision-gates.md`

Current status:
- REQUIRED NOW gates are RESOLVED:
  - .NET 10 LTS — `docs/plan/decisions/ADR-0001-dotnet-10-lts-baseline.md`
  - EF Core 10 + Npgsql — `docs/plan/decisions/ADR-0002-ef-core-npgsql-baseline.md`
- other listed Foundation technology gates remain deferrable or later-phase for the first thin slice.
- P4 Foundation implementation readiness is READY.
- `FW-IMP-001 — repository solution skeleton` is COMPLETED.
- next repository-defined implementation package is `FW-IMP-002 — configuration/context/error primitives`.


## FW-IMP-001 implementation evidence

Status: COMPLETED

Canonical evidence:
- `docs/plan/01-foundation/fw-imp-001-implementation.md`

Verified:
- .NET 10 solution skeleton exists;
- project-reference dependency direction is encoded;
- restore/build passed on self-hosted runner;
- no domain logic, persistence schema/migration, auth provider, OpenAPI tooling, UI implementation or deployment was introduced.

Current next package:
- `FW-IMP-002 — configuration/context/error primitives`
