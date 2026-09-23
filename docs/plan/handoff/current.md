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
- PARTY-IMP-003 — Add Turkish Tax Identity: COMPLETED
- PARTY-IMP-004 — Manage Party Role Lifecycle: COMPLETED

## PARTY-IMP-004 evidence
Canonical report:
- `docs/plan/03-cariler/party-imp-004-implementation.md`

Readiness:
- `docs/plan/03-cariler/p5-fourth-slice-readiness.md`

Implementation:
- implementation commit `f640fceae01fe734a273c1f3adc4136d497be28d`
- tested deploy commit `f746139981d79941d414e318ebcab017f8ae4df8`

Foundation Build:
- run `35910331829`
- job `107348248484`
- SUCCESS
- frontend tests 15 / 15 PASS
- .NET Release build PASS, 0 warnings / 0 errors
- Foundation targeted tests 50 / 50 PASS
- EF pending model PASS; no model change
- API smoke PASS
- project references PASS

Foundation Test Deploy:
- run `35910331820`
- job `107348248383`
- SUCCESS
- migration safety PASS for 5 committed migrations
- EF model drift PASS
- remote TEST preflight PASS
- no new PARTY-IMP-004 migration
- migration count remains 5
- runtime grants PASS
- health/readiness PASS
- TEST /parties/new = 200
- unauthenticated Party Role lifecycle POST = 401
- OpenAPI = 200
- runner-to-TEST smoke PASS

A real authenticated TEST role lifecycle mutation is not claimed.

## PARTY-IMP-004 boundary
Implemented:
- existing CUSTOMER/SUPPLIER role ACTIVE ↔ INACTIVE
- party.role.manage
- trusted-company Party/role lookup
- expected-version optimistic concurrency
- same-state conflict
- mandatory reason for deactivation
- audit + durable idempotency
- protected role-state API
- lifecycle controls on /parties/new
- no EF model/schema change

Deferred:
- soft/fuzzy duplicate review
- Contact/Communication/Address
- Party deactivate/reactivate
- Party Merge
- Party External Mapping
- Tax Identity read/edit/deactivate/provider/non-TR
- Sales/Purchasing role eligibility enforcement
- Finance integration

## Current phase
P5 — Core application implementation
Status: IMPLEMENTATION IN PROGRESS

## Current task
Define the next smallest coherent Parties vertical slice.

Work-package ID:
- NOT ASSIGNED.

Repository follow-up candidates:
- accepted soft duplicate candidate/review flow;
- Contact Person / Communication Point / Address;
- Party deactivate/reactivate;
- Party Merge;
- Party External Mapping;
- broader Tax Identity read/lifecycle/provider/non-TR support where later required.

Do not invent PARTY-IMP-005 before exact scope is frozen.

## Planning progress
- Master section-8 planning coverage: 9 / 30 = 30.0%
- P2 core commercial planning: 8 / 8 = 100.0%

These are planning metrics, not implementation-progress metrics.

## Full Test Day
Still deferred:
- concurrent Party Code creation
- concurrent Party Role first activation
- concurrent role lifecycle transitions
- concurrent deterministic Tax Identity collision
- cross-company identity/IDOR matrix
- broad permission matrix
- authenticated browser Party lifecycle E2E
- consuming Sales/Purchasing role-state eligibility
- provider verification reconciliation
- high-volume duplicate/identity search
- PII/security regression
- performance/load
- backup/restore
