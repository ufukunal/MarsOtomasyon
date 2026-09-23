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
- PARTY-IMP-005 — Deactivate Party: COMPLETED

## PARTY-IMP-005 evidence
Canonical report:
- `docs/plan/03-cariler/party-imp-005-implementation.md`

Readiness:
- `docs/plan/03-cariler/p5-fifth-slice-readiness.md`

Implementation:
- readiness commit `73cf3921becc615ef56e8c871b67882fa8ef411b`
- tested implementation/deploy commit `4ab2a30bfffdf64c9e5b7634708ad4cdacdde33a`

Foundation Build:
- run `35922536747`
- job `107389743246`
- SUCCESS
- frontend tests 16 / 16 PASS
- .NET Release build PASS, 0 warnings / 0 errors
- Foundation targeted tests 55 / 55 PASS
- migration safety count 5
- EF pending-model PASS; no model change
- API smoke PASS
- project references PASS

Foundation Test Deploy:
- run `35922536768`
- job `107389744421`
- SUCCESS
- migration safety PASS for 5 migrations
- EF model drift PASS
- remote TEST preflight PASS
- no new PARTY-IMP-005 migration
- migration count remains 5
- runtime grants PASS
- health/readiness PASS
- TEST /parties/new = 200
- unauthenticated Party deactivate POST = 401
- OpenAPI = 200
- runner-to-TEST smoke PASS

A real authenticated TEST Party deactivation is not claimed.

## PARTY-IMP-005 boundary
Implemented:
- Party ACTIVE → INACTIVE only
- party.deactivate
- trusted-company Party lookup
- expected-version optimistic concurrency
- mandatory reason
- already INACTIVE/MERGED conflicts
- audit + durable idempotency
- protected deactivate API
- deactivation control on /parties/new
- no EF model/schema change

Deferred:
- Party reactivate
- soft/fuzzy duplicate review
- Contact/Communication/Address
- Party External Mapping
- Party Merge
- Tax Identity follow-up
- consuming Sales/Purchasing Party eligibility
- Finance integration

## Current phase
P5 — Core application implementation
Status: IMPLEMENTATION IN PROGRESS

## Current task
PARTY-IMP-006 — Party Master Completion Tranche.

Status:
- READY FOR IMPLEMENTATION.

Canonical readiness:
- `docs/plan/03-cariler/p5-party-master-completion-readiness.md`

Owner direction:
- broaden Party implementation scope instead of one package per small capability;
- normal technical implementation choices belong to active skills unless they introduce a new business rule.

Included:
- Party list/detail/read and legal/display identity edit;
- Contact Person / Communication Point;
- Address lifecycle/default-by-purpose;
- existing TR VKN/TCKN read/masking/lifecycle;
- Party External Mapping;
- explicit same-company Party Merge + lineage;
- required permission/API/UI/persistence/migration/audit/idempotency/concurrency/targeted tests.

Deferred:
- fuzzy candidate generation/scoring/thresholds;
- Party Reactivation until duplicate/legal-identity rerun prerequisite is resolved;
- provider/GIB and non-TR tax behavior;
- Communications consent/preferences;
- Sales/Purchasing/Finance implementation;
- production deployment;
- Full Test Day.

The unresolved Reactivation question does not block PARTY-IMP-006 because Reactivation is outside this package.

## Planning progress
- Master section-8 planning coverage: 9 / 30 = 30.0%
- P2 core commercial planning: 8 / 8 = 100.0%

These are planning metrics, not implementation-progress metrics.

## Full Test Day
Still deferred:
- concurrent Party Code creation
- concurrent role activation/lifecycle transitions
- concurrent deterministic Tax Identity collision
- concurrent Party deactivation and related mutation races
- cross-company identity/IDOR matrix
- broad permission matrix
- authenticated browser Party lifecycle E2E
- consuming Sales/Purchasing eligibility
- provider verification reconciliation
- high-volume duplicate/identity search
- PII/security regression
- performance/load
- backup/restore
