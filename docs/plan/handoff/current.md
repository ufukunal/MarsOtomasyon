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
- P4 Foundation implementation: COMPLETED
- FW-IMP-001 through FW-IMP-008: COMPLETED
- PARTY-IMP-001 — Create Party Core Identity: COMPLETED

## PARTY-IMP-001 evidence

Canonical implementation report:
- `docs/plan/03-cariler/party-imp-001-implementation.md`

Authorization decision:
- `docs/plan/decisions/ADR-0005-mars-erp-permission-authority.md`

Migration:
- commit `1c6983bea3f046522183a2a5f366f2b868a721f0`
- `src/Mars.Infrastructure/Persistence/Migrations/Parties/20260923155536_PartyImp001CoreIdentity.cs`

Foundation Build:
- run `35885316247`
- job `107263853019`
- SUCCESS
- frontend tests 12 / 12 PASS
- .NET Release build PASS, 0 errors
- Foundation targeted tests 35 / 35 PASS
- committed migration verification PASS
- EF model drift PASS
- API smoke PASS
- project references PASS

Foundation Test Deploy:
- tested deploy commit `b1305ad0d48b6ef6e5522a9f6f66a46a2926902c`
- run `35885323206`
- job `107263878180`
- SUCCESS
- migration safety PASS for 3 migrations
- remote TEST preflight PASS
- Party migration APPLIED
- runtime grants PASS
- health/readiness PASS
- TEST /parties/new = 200
- unauthenticated POST /api/v1/parties = 401
- OpenAPI = 200
- runner-to-TEST smoke PASS
- EF migration count = 3

PARTY-IMP-001 does not claim a live authenticated browser/API Party create against TEST. Broad auth/browser E2E remains Full Test Day.

## PARTY-IMP-001 boundary preserved

Implemented:
- company-scoped Party core identity
- PERSON / ORGANIZATION
- caller-supplied Party Code
- legal/display identity
- ACTIVE initial state
- optimistic version
- PostgreSQL Permission Grant + party.create evaluation
- audit
- durable idempotency
- POST /api/v1/parties
- /parties/new
- additive EF migration

Deferred:
- Party Role
- Tax Identity
- Contact / Communication
- Address
- External Mapping
- Merge
- soft/fuzzy duplicate review
- Settings/Numbering allocator
- permission administration UI/role-group model
- Finance projections
- Sales/Purchasing Party consumption

## Current phase
P5 — Core application implementation
Status: IMPLEMENTATION IN PROGRESS

## Current task
Define the next smallest coherent Parties vertical slice.

Work-package ID:
- NOT ASSIGNED.

Repository backlog identifies follow-up requirements but does not freeze their order:
- soft duplicate candidate/review flow;
- Tax Identity records and deterministic collision rules;
- Party Role activation;
- contacts/addresses;
- lifecycle/merge in later slices.

Do not invent PARTY-IMP-002 before the exact next scope is defined from frozen PLAN-003 + PLAN-010.

## Planning progress
- Master section-8 sequence: 9 / 30 = 30.0%
- P2 core commercial: 8 / 8 = 100.0%

P5 implementation completion does not change these planning percentages.

## Full Test Day
Still deferred:
- concurrent Party Code race
- deterministic Tax Identity collision concurrency when implemented
- stale Party edits
- cross-company IDOR matrix
- broad permission matrix
- authenticated browser Party lifecycle E2E
- high-volume fuzzy duplicate search
- downstream snapshot immutability
- security/privacy regression
- performance/load
- backup/restore
