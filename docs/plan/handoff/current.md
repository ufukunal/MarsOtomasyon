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

## PARTY-IMP-006 evidence

Canonical readiness:
- `docs/plan/03-cariler/p5-party-master-completion-readiness.md`

Canonical implementation:
- `docs/plan/03-cariler/party-imp-006-implementation.md`

Commits:
- implementation `d3b21ab936bfb3b9c6d602d799e8ab587ea4ee05`
- migration-verification fix `20f9635d58ff0289e0aea098f4ec0e47399c7d93`
- generated migration `2a4bb99e12e65c0a4c121755d764e7bca7feda85`
- final tested commit `55b7e78bc2eabcf986e8318f60296f0f3f9cd223`

Foundation Build:
- run `35934312246`
- job `107427788711`
- SUCCESS
- frontend tests 18 / 18 PASS
- Foundation targeted tests 61 / 61 PASS
- Release build 0 warnings / 0 errors
- EF pending-model PASS

Foundation Test Deploy:
- run `35934312470`
- job `107427788946`
- SUCCESS
- migration safety count 6
- TEST migration applied; deployed migration count 6
- /parties 200
- /parties/new 200
- live/ready 200 / 200
- protected Party master endpoints unauthenticated 401
- OpenAPI 200
- runner-to-TEST smoke PASS

No real authenticated TEST Party master mutation is claimed.

PARTY-IMP-006 completed the frozen broad tranche:
- Party list/detail/edit;
- Contact/Communication;
- Address;
- TR Tax Identity read/masking/lifecycle;
- External Mapping;
- explicit Merge/lineage.

Still deferred:
- fuzzy duplicate candidate generation;
- Party Reactivation;
- provider/GİB/non-TR identity;
- Communications consent/preferences;
- Sales/Purchasing eligibility;
- Finance behavior.

## Current phase
P5 — Core application implementation
Status: IMPLEMENTATION IN PROGRESS

## Current task
PRODUCT-IMP-001 — Product Master Completion Tranche.

Status:
- IMPLEMENTATION IN PROGRESS.

Canonical readiness:
- `docs/plan/04-urun-stok/p5-product-master-completion-readiness.md`

Owner direction:
- broad coherent Product package;
- do not split Product/Variant/UOM/Barcode/Category/External Mapping into micro-packages.

Included:
- Product core create/list/detail/edit/lifecycle;
- UOM + initial Base UOM + alternate Product/Variant UOM;
- Variant lifecycle;
- Barcode lifecycle;
- normalized Category hierarchy/assignments;
- generic Product External Mapping;
- protected API/Mars.Web/persistence/migration/audit/idempotency/concurrency/targeted tests.

Deferred:
- Warehouse/Location/Inventory Ledger/Reservation/Lot/Serial;
- stock/value/cost authority;
- Base UOM replacement;
- post-use STOCKABLE/tracking changes;
- Variant EAV attributes;
- provider-specific sync/GS1 verification;
- production deployment;
- Full Test Day.

Implementation decision:
- conversion factor physical storage numeric(28,9);
- Product External Mapping read uses product.read and mutation uses product.edit; no new permission namespace invented.

## Planning progress
- Active state metric: Master 9 / 30 = 30.0%
- P2 core commercial planning: 8 / 8 = 100.0%
- `docs/db/acceptance-criteria.md` separately records 10 / 30 = 33.3%; this inconsistency remains for explicit normalization.

## Full Test Day
Still deferred:
- Party broad authenticated E2E / permission matrix / concurrency;
- Product future heavy tests after Product implementation;
- high-volume search;
- PII/security regression;
- performance/load;
- backup/restore.
