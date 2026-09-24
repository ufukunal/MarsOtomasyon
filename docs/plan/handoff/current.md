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

## PRODUCT-IMP-001 evidence

Canonical readiness:
- docs/plan/04-urun-stok/p5-product-master-completion-readiness.md

Canonical implementation:
- docs/plan/04-urun-stok/product-imp-001-implementation.md

Commits:
- implementation 817dfea81acbf1e93586b030412bd492f8a3ba20
- migration 9b0731792a8641b632c612b7abb6f74799dd2aa4
- tested commit ac92911a8f5fcda070622b084216a43a70ad7d77

Foundation Build:
- run 35981641268
- job 107574669148
- SUCCESS
- frontend 19 / 19
- Foundation 66 / 66
- Release 0 warnings / 0 errors
- EF pending-model PASS

Foundation Test Deploy:
- run 35981641137
- job 107574851341
- SUCCESS
- migration safety count 7
- TEST migration 6 -> 7
- /products 200
- live/ready 200 / 200
- protected Product routes 401 unauthenticated
- OpenAPI 200
- smoke PASS

No real authenticated TEST Product mutation is claimed.

Completed Product master:
- Product core/list/detail/edit/lifecycle
- UOM/Product-UOM
- Variant
- Barcode
- Category/Product Category
- Product External Mapping
- protected API/Mars.Web
- generated Product migration

Still deferred:
- Base UOM replacement
- post-use STOCKABLE/tracking transitions
- UOM transaction scale/fraction policy
- generic Variant EAV
- provider/GS1 behavior
- Inventory physical authority
- Finance valuation/cost

## Current phase
P5 — Core application implementation
Status: IMPLEMENTATION IN PROGRESS

## Current task
INVENTORY-IMP-001 — Inventory Authority & Traceability Tranche.

Status:
- READY FOR IMPLEMENTATION
- implementation not started

Canonical readiness:
- docs/plan/04-urun-stok/p5-inventory-authority-traceability-readiness.md

Scope freeze baseline:
- repository HEAD before readiness mutation: f6a15146b68f1ec6016c14437ad7d11159ea1a7f
- readiness commit: c13fac0612c6b0894d2ffcabf766dcf52f3e4003
- no pre-existing Inventory Domain/Application/Persistence/API/Web implementation
- no pre-existing Inventory migration
- committed migration baseline: 7

Broad included authority:
- Warehouse master
- Location master/hierarchy
- controlled dispositions AVAILABLE / QUARANTINE / QUALITY_HOLD / REWORK / DAMAGED / TRANSIT
- Lot / Serial trace identity
- append-oriented Inventory Ledger
- append-oriented non-physical Reservation authority
- Product/Variant/UOM/tracking enforcement
- stock/availability/movement/reservation/trace read surfaces
- protected API/Mars.Web, audit, idempotency, concurrency and additive Inventory migration

Explicit boundary:
- no generic public movement-post endpoint
- no free-form Reservation mutation disconnected from Sales Order line/version
- Warehouse receiving/put-away/pick/pack/stage/load/transfer/count/replenishment/damage/scrap/offline workflows remain Warehouse-owned
- Finance/Costing retains valuation/current-cost/COGS authority
- no production deployment
- no Full Test Day

Permission reconciliation:
- Warehouse/Location uses later frozen PLAN-006 warehouse.* / warehouse.location.* namespace
- Inventory quantity read keeps inventory.stock.read
- Inventory-owned trace read keeps inventory.trace.read
- Foundation Permission Grant remains Actor + trusted Company + PermissionCode
- no separate actor-to-Warehouse assignment model is invented in this tranche

## Planning progress
- Active state metric: Master 9 / 30 = 30.0%
- P2 core commercial planning: 8 / 8 = 100.0%
- docs/db/acceptance-criteria.md separately records 10 / 30 = 33.3%; inconsistency remains for explicit normalization.

## Full Test Day
Still deferred:
- broad authenticated Product E2E / permission matrix / concurrency
- Inventory future heavy tests after implementation
- high-volume lookup
- performance/load
- security regression
- backup/restore
