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
- PARTY-IMP-002 — Activate Party Role: COMPLETED

## PARTY-IMP-002 evidence
Canonical implementation report:
- `docs/plan/03-cariler/party-imp-002-implementation.md`

Readiness:
- `docs/plan/03-cariler/p5-second-slice-readiness.md`

Scope commit:
- `e3052ecfc67a0831a745354eb94d4ca1f08c5c6f`

Implementation commit:
- `41c1015fceb1ff1c6efef3b55d35338fb4c64f88`

Migration:
- commit `b540589291d9e115175649f29fab4ef37c943ebf`
- `src/Mars.Infrastructure/Persistence/Migrations/Parties/20260923163146_PartyImp002RoleActivation.cs`

Foundation Build:
- run `35889499695`
- job `107278081364`
- SUCCESS
- frontend tests 13 / 13 PASS
- .NET Release build PASS, 0 warnings / 0 errors
- Foundation targeted tests 40 / 40 PASS
- Party Role domain/application/model verification PASS
- EF model drift PASS
- API smoke PASS
- project references PASS

Foundation Test Deploy:
- tested deploy commit `09e1eed87edf23da4002127337185f13dc3a6366`
- run `35889499708`
- job `107278082716`
- SUCCESS
- migration safety PASS for 4 migrations
- remote TEST preflight PASS
- Party Role migration APPLIED
- runtime grants PASS
- health/readiness PASS
- TEST /parties/new = 200
- unauthenticated POST /api/v1/parties/{partyPublicId}/roles = 401
- OpenAPI = 200
- runner-to-TEST smoke PASS
- EF migration count = 4

A real authenticated TEST role activation is not claimed. Normal smoke intentionally proves the unauthenticated boundary without inventing an auth bypass.

## PARTY-IMP-002 boundary preserved
Implemented:
- CUSTOMER / SUPPLIER Party Role activation
- one normalized Parties-owned Party Role child
- party.role.manage
- trusted-company Party lookup
- unique Party + RoleType
- optimistic role version
- audit + durable idempotency
- protected role activation API
- post-create role activation on /parties/new
- additive EF migration

Deferred:
- role deactivate/reactivate
- role-specific defaults/codes
- Tax Identity
- Contact / Communication
- Address
- External Mapping
- soft duplicate review
- Party lifecycle/merge
- Finance balance/risk/ledger
- Sales/Purchasing eligibility integration

## Current phase
P5 — Core application implementation
Status: IMPLEMENTATION IN PROGRESS

## Current task
Define the next smallest coherent Parties vertical slice.

Work-package ID:
- NOT ASSIGNED.

Repository follow-up requirements remain:
- Tax Identity records and deterministic collision rules;
- accepted soft duplicate candidate/review flow;
- contacts/addresses;
- role lifecycle after activation;
- lifecycle/merge in later slices.

Repository does not freeze their implementation order. Do not invent PARTY-IMP-003 before exact scope is defined from frozen PLAN-003 + PLAN-010.

## Planning progress
- Master section-8 sequence: 9 / 30 = 30.0%
- P2 core commercial: 8 / 8 = 100.0%

## Full Test Day
Still deferred:
- concurrent Party Code race
- CUSTOMER/SUPPLIER role activation concurrency
- future Tax Identity collision concurrency
- stale Party/role edits
- cross-company IDOR matrix
- broad permission matrix
- authenticated browser Party lifecycle / dual-role E2E
- high-volume fuzzy duplicate search
- downstream snapshot immutability
- Finance proof of no role-activation ledger effect
- security/privacy regression
- performance/load
- backup/restore
