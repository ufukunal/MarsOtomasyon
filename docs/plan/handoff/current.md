# Current Handoff

## Repository
- Repository: ufukunal/MarsOtomasyon
- Allowed branch: main
- Current HEAD: verify from Git at session start
- Master plan: docs/plan/master-project-plan.md
- Session protocol: docs/ai/session-execution-protocol.md

## Completed
- P2 Core Commercial Workflow Planning: COMPLETED / FROZEN
- P3 Logical Database Model: COMPLETED / FROZEN
- P4 Foundation readiness: COMPLETED
- P4 Foundation implementation: COMPLETED
- FW-IMP-001 through FW-IMP-008: COMPLETED

## FW-IMP-008 evidence

Canonical report:
- `docs/plan/01-foundation/fw-imp-008-implementation.md`

Final tested implementation:
- commit: `bf9b3882dbc91ee801d169a62587c7658baaa63b`
- Foundation Build: run `35868005913`, job `107204452548` — SUCCESS
- Foundation Test Deploy: run `35868151531`, job `107205339908` — SUCCESS

Verified:
- frontend targeted tests: 11 / 11 PASS
- Vite production build: PASS
- .NET Release build: PASS — 0 errors
- Foundation targeted tests: 30 / 30 PASS
- committed migration safety/model drift: PASS; no new migration
- remote TEST preflight: PASS
- API/migrator/Web image build and TEST deployment: PASS
- TEST /proof: 200
- TEST /health/live: 200
- TEST /health/ready: 200
- unauthenticated POST /api/v1/foundation/proof: 401
- OpenAPI: 200 and contains proof route
- runner-to-TEST smoke: PASS

Proof boundary:
- non-domain Foundation-only command
- trusted authenticated execution context
- existing idempotency/audit/outbox persistence
- single PostgreSQL transaction
- protected API + Mars.Web route
- no ERP business entity/table/rule
- no new package or migration

A live authenticated browser-to-DB mutation is not claimed. No login/account UI or TEST authentication bypass was invented; that heavy authenticated E2E remains deferred.

## Current phase
P5 — Core application implementation
Status: SCOPE DEFINITION REQUIRED

## Current task
Define the first Parties implementation vertical slice.

Master P5 dependency order starts with Parties, but the repository does not yet assign a dedicated implementation work-package ID or exact first slice. Do not invent either before reviewing the frozen Party and logical DB contracts.

Required next sources:
- `docs/plan/03-cariler/`
- `docs/db/`
- Foundation implementation evidence, especially FW-IMP-008
- master P5 dependency order

Next-session objective:
- choose the smallest coherent Party implementation slice;
- map it to frozen logical DB contracts;
- define physical migration/domain/application/API/UI boundaries;
- identify any genuine business/legal/authorization blocker;
- only then record a dedicated implementation work-package ID and proceed if safe.

## Deferred technology choices
Still not selected:
- production secret store
- production reverse proxy/tunnel
- production DNS/ingress/TLS
- structured logging/metrics stack
- Desktop shell
- Mobile shell
- optional scheduling technology unless required by a concrete future task

## Planning progress
- Master section-8 sequence: 9 / 30 = 30.0%
- P2 core commercial: 8 / 8 = 100.0%

Implementation completion does not increment section-8 planning completion.

## Full Test Day
Still deferred by policy, including:
- real authenticated browser vertical-proof E2E
- full auth/permission matrix
- browser/Desktop/Mobile E2E
- broad PostgreSQL integration/concurrency
- Worker/provider delivery E2E
- backup/restore
- performance/load
- security regression
- cross-platform regression
