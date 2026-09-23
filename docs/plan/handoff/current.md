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

## PARTY-IMP-003 evidence
Canonical report:
- `docs/plan/03-cariler/party-imp-003-implementation.md`

Readiness:
- `docs/plan/03-cariler/p5-third-slice-readiness.md`

Implementation:
- initial implementation commit `cb22e1fca1d1cd0ebeab98acbd3db1644297c0f8`
- final generated migration commit `70876a0fcb84368d9b3ec9d305eaf8eacf0c19b2`
- tested deploy commit `ec5f2c932da8f717e7e56d1e612ea0ba2dbaa36c`

Foundation Build:
- run `35894175633`
- job `107293838961`
- SUCCESS
- frontend tests 14 / 14 PASS
- .NET Release build PASS, 0 warnings / 0 errors
- Foundation targeted tests 45 / 45 PASS
- EF model drift PASS
- API smoke PASS
- project references PASS

Foundation Test Deploy:
- run `35894175558`
- job `107293839344`
- SUCCESS
- migration safety PASS for 5 committed migrations
- remote TEST preflight PASS
- PARTY-IMP-003 migration APPLIED
- runtime grants PASS
- health/readiness PASS
- TEST /parties/new = 200
- unauthenticated Tax Identity POST = 401
- OpenAPI = 200
- runner-to-TEST smoke PASS
- EF migration count = 5

A real authenticated TEST Tax Identity mutation is not claimed.

## PARTY-IMP-003 boundary
Implemented:
- one ACTIVE Turkish Tax Identity child on existing Party
- TR jurisdiction only
- VKN 10-digit / TCKN 11-digit structural validation
- party.tax_identity.manage
- trusted-company Party lookup
- deterministic active company + jurisdiction + scheme + value conflict
- composite Party/company FK
- public Tax Identity UUID
- audit without raw VKN/TCKN
- durable idempotency
- protected add API
- tax identity add UI on /parties/new
- additive EF migration

Deferred:
- checksum validation
- GİB/e-document/provider enrollment verification
- generic non-TR Tax Identity schemes
- Tax Identity read/read_full/list/edit/deactivate
- soft/fuzzy duplicate review
- contacts/addresses
- role lifecycle after activation
- Party lifecycle/merge
- Finance/Sales/Purchasing integration

## Current phase
P5 — Core application implementation
Status: IMPLEMENTATION IN PROGRESS

## Current task
- PARTY-IMP-004 — Manage Party Role Lifecycle
- status: READY / IMPLEMENTATION NOT STARTED
- canonical readiness: docs/plan/03-cariler/p5-fourth-slice-readiness.md

Frozen scope:
- existing CUSTOMER/SUPPLIER role ACTIVE ↔ INACTIVE only;
- party.role.manage;
- trusted-company Party/role lookup;
- expected-version optimistic concurrency;
- mandatory reason for deactivation;
- reactivation does not invent a mandatory reason;
- audit + durable idempotency;
- POST /api/v1/parties/{partyPublicId}/roles/{role}/state;
- lifecycle control on existing /parties/new journey;
- no EF model change/migration expected.

Still deferred:
- soft duplicate candidate/review;
- contacts/addresses;
- Party lifecycle/merge;
- Tax Identity lifecycle/provider/non-TR;
- Sales/Purchasing eligibility implementation.

## Planning progress
- Master section-8 planning coverage: 9 / 30 = 30.0%
- P2 core commercial planning: 8 / 8 = 100.0%

These are planning metrics, not implementation-progress metrics.

## Full Test Day
Still deferred:
- concurrent Party Code creation
- concurrent Party Role activation
- concurrent deterministic Tax Identity collision
- cross-company same Tax Identity isolation
- stale Party/role/tax identity updates
- cross-company IDOR matrix
- broad permission matrix
- authenticated browser Party lifecycle E2E
- future provider verification reconciliation
- high-volume fuzzy/Tax Identity lookup
- PII logging/export/security regression
- performance/load
- backup/restore
