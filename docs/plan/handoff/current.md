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

## INVENTORY-IMP-001 evidence

Canonical readiness:
- docs/plan/04-urun-stok/p5-inventory-authority-traceability-readiness.md

Canonical implementation:
- docs/plan/04-urun-stok/inventory-imp-001-implementation.md

Commits:
- implementation sequence begins fcafae3e28c2d347f27f6dfa4379033f8e66991a
- migration f4dc4cdf60bff8b5845347b6c50fbac5d64e3206
- final tested commit edcc24f1200b70aad102fc510ad7bec60c4515e7

Foundation Build:
- run 35998315605
- job 107628488066
- SUCCESS
- frontend 20 / 20
- Foundation targeted 74 PASS
- Release 0 warnings / 0 errors
- EF pending-model PASS
- Inventory protected API/OpenAPI smoke PASS

Foundation Test Deploy:
- run 35998315582
- job 107628488098
- SUCCESS
- migration safety count 8
- deployed migration count 8
- /parties 200
- /products 200
- /inventory 200
- live/ready 200 / 200
- protected Inventory routes unauthenticated 401
- OpenAPI 200
- runner-to-TEST smoke PASS

No real authenticated TEST Inventory mutation is claimed.

Completed Inventory authority:
- Warehouse / Location master and lifecycle
- controlled AVAILABLE / QUARANTINE / QUALITY_HOLD / REWORK / DAMAGED / TRANSIT dispositions
- Lot / Serial trace identity
- append-oriented Inventory Ledger
- append-oriented non-physical Reservation authority
- stock / position / movement / reservation / trace reads
- protected API and Mars.Web /inventory
- Inventory migration applied to TEST

Still deferred:
- Warehouse receiving/put-away/pick/pack/stage/load/transfer/count/replenishment/damage/scrap/offline workflows
- Sales/Purchasing/Returns source-document implementations
- Finance valuation/current-cost/COGS
- Product Base UOM replacement and post-use STOCKABLE/tracking transition
- broad authenticated Inventory E2E and heavy concurrency/security/performance coverage

## Current phase
P5 — Core application implementation
Status: IMPLEMENTATION IN PROGRESS

## Current task
SALES-IMP-001 — Sales Commercial & Fulfillment Authority Tranche.

Status:
- READY FOR IMPLEMENTATION
- implementation not started

Canonical readiness:
- docs/plan/05-satis/p5-sales-commercial-fulfillment-readiness.md

Scope-freeze baseline:
- main HEAD before readiness mutation: 7422aea2f5f750b53187578992fe64fdc5e4f7d4
- readiness commit: 2b2a0629bf8cc0726a3fce65b2ed9065f428f6f7
- no pre-existing Sales Domain/Application/Persistence/API/Web implementation
- no Migrations/Sales
- no Finance Account Ledger/Valuation implementation
- no Warehouse Pick/Pack/Stage/Load implementation
- committed migration baseline: 8

Broad included authority:
- Quote/revision/line lifecycle
- partial/repeated Quote conversion
- Sales Order/effective version/line lifecycle
- controlled Order amendment
- approval evidence/SoD
- explicit Inventory Reservation integration
- Warehouse resource-scope authorization required for Dispatch
- Dispatch commercial authority
- atomic Dispatch POST + Inventory STOCK OUT + Reservation consume
- Dispatch reversal
- Invoice DRAFT/source/calculation commercial authority
- optional Proforma inside the same package
- protected API/Mars.Web/read projections
- additive Sales/cross-support migration and TEST deployment

Explicit boundary:
- Invoice POST/REVERSE is not in SALES-IMP-001 because Finance Account/Valuation/Dispatch Cost Bridge authority is absent
- Collection/settlement remains Finance-owned
- Pick/Pack/Stage/Load remains Warehouse-owned
- no generic stock mechanism inside Sales
- no Sales Reservation truth
- no TCMB/e-document/provider implementation
- no production deployment
- no Full Test Day

Key reconciliation decisions:
- later PLAN-006 Warehouse operational permissions supersede PLAN-002 sales.dispatch.pick/pack ownership
- Dispatch POST remains Sales-owned and must also validate Warehouse access scope
- existing Inventory persistence can join an ambient MarsDbContext transaction
- no-policy commercial approval is fail-closed
- caller-supplied Sales document number is temporary until Settings/Numbering exists
- TRY minor unit is 2; non-TRY finalization remains fail-closed until server-side currency/FX authority exists

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


## SALES-IMP-001 closure

Canonical implementation:
- docs/plan/05-satis/sales-imp-001-implementation.md

Tested commit:
- c99b1a3191fc5c6823734bd27f074ef10a8094a3

Foundation Build:
- run 36026678334
- job 107724878496
- SUCCESS
- frontend 21 / 21 PASS
- targeted Foundation 87 / 87 PASS
- Release 0 warnings / 0 errors
- migration safety 10 PASS
- EF pending model PASS

Foundation Test Deploy:
- run 36026678557
- job 107724879932
- SUCCESS
- deployed migration count 10
- /sales 200
- /parties /products /inventory 200
- live/ready 200
- protected Sales routes 401 unauthenticated
- OpenAPI Sales surface expected
- Invoice POST/REVERSE absent
- smoke PASS

No authenticated TEST Sales mutation is claimed.


## PURCHASING-IMP-001 closure

Canonical implementation:
- docs/plan/07-satinalma/purchasing-imp-001-implementation.md

Tested commit:
- 50ec242e5471743bbdc9ad43bc69626166b1679f

Migration:
- 20260924201517_PurchasingImp001CommercialReceiptInvoiceAuthority
- migration commit cb00a419241ead9f925731fe7db0c1e808125170
- migration safety count 11
- EF pending-model PASS

Foundation Build:
- run 36054248023
- job 107817148334
- SUCCESS
- frontend 22 / 22 PASS
- targeted Foundation 97 / 97 PASS
- Release 0 warnings / 0 errors
- Purchasing protected API/OpenAPI smoke PASS
- Supplier Invoice POST/REVERSE absent

Foundation Test Deploy:
- run 36054247926
- job 107818562329
- SUCCESS
- deployed migration count 11
- /purchasing /sales /inventory /products /parties = 200
- health live/ready = 200
- protected Purchasing routes unauthenticated = 401
- OpenAPI Purchasing surface expected
- Supplier Invoice POST/REVERSE absent
- smoke PASS

No real authenticated TEST Purchasing mutation is claimed.

Completed authority:
- Purchase Order lifecycle + controlled remainder-decrease version amendment
- Goods Receipt DRAFT/READY/POST/cancel/reversal
- Warehouse scope guard for receipt mutations
- stockable receipt Inventory STOCK IN to QUARANTINE
- service/non-stock receipt no physical movement
- cumulative receipt source caps
- Supplier Invoice DRAFT create/replace/cancel
- 2-way / 3-way match evidence
- direct financial-only invoice guard + exception approval evidence
- cumulative active Invoice DRAFT source caps
- Purchase Return source preview boundary
- protected API and Mars.Web /purchasing

Deferred:
- Supplier Invoice POST/REVERSE and Supplier Payable
- Payment/settlement
- Inventory valuation/landed cost
- Quality implementation
- Warehouse operations
- Purchase Return execution
- providers
- production
- Full Test Day


## WAREHOUSE-IMP-001 closure

Canonical implementation:
- docs/plan/06-ambar-depo/warehouse-imp-001-implementation.md

Runtime-tested commit:
- 81efd9c347118db24d8945f4d49e63bf99baaf70

Migrations:
- 20260924220511_WarehouseImp001ExecutionInventoryControlAuthority
- 20260924223900_WarehouseImp001SalesDispatchSourceAllocations
- final deployed migration count 13

Foundation Build:
- run 36068722249
- job 107864429802
- SUCCESS
- frontend 23 / 23 PASS
- targeted Foundation 108 / 108 PASS
- Release 0 warnings / 0 errors
- EF pending-model clean
- Warehouse protected API/OpenAPI smoke PASS

Foundation Test Deploy:
- run 36068722239
- job 107864430160
- SUCCESS
- migration safety count 13
- deployed migration count 13
- /warehouse /sales /purchasing /inventory /products /parties = 200
- health live/ready = 200
- protected Warehouse routes unauthenticated = 401
- OpenAPI Warehouse surface expected
- Warehouse Dispatch POST / Goods Receipt POST authority absent
- smoke PASS

No real authenticated TEST Warehouse mutation is claimed.

Completed authority:
- receiving/quarantine read;
- disposition/damage/put-away/replenishment;
- FEFO/FIFO pick with controlled override;
- Pick/Pack/Stage/Load STOCK = NONE;
- Sales-owned multi-source Dispatch source allocation;
- allocation-aware Sales Dispatch physical POST/reversal;
- transfer ISSUE/TRANSIT/partial RECEIVE/reconcile/close/reverse;
- Stock Count snapshot/intervening/recount/approval/negative adjustment/close/reverse;
- positive Count fail-closed without Finance valuation;
- scrap approval/SoD + physical OUT;
- Warehouse/Location open-work deactivation blocker;
- offline operation journal;
- protected API and Mars.Web /warehouse.

Deferred:
- Finance valuation/account/COGS/write-off authority;
- Quality implementation;
- providers / Device Layer / scanner drivers;
- production;
- Full Test Day.

## Current task

Finance / Treasury Broad Implementation Tranche Definition

Status:
- SCOPE DEFINITION REQUIRED
- NOT STARTED
- implementation work-package ID UNASSIGNED

Planning source:
- frozen PLAN-007
- docs/plan/09-finans-kasa-banka/

Required next action:
- perform repository reconciliation;
- inspect current Sales / Purchasing / Inventory / Warehouse financial handoff points;
- freeze one broad coherent Finance / Treasury implementation tranche;
- define exact Account Ledger, Cash/Bank, settlement, posting-period, valuation and Dispatch cost-bridge authority;
- define how currently fail-closed Sales Invoice POST, Supplier Invoice POST and positive Count Adjustment become Finance-integrated;
- assign the Finance implementation package ID only after exact scope freeze.

Do not:
- repeat global/module planning;
- create Finance micro-package IDs before scope freeze;
- implement before exact broad scope is frozen;
- run production;
- run Full Test Day.

## Planning progress
- portfolio planning: 30 / 30 = 100.0%
- detailed master planning: 30 / 30 = 100.0%

## FINANCE-IMP-001 scope freeze / current handoff

This trailing section supersedes stale earlier "Current task" text retained for historical session evidence.

Current task:
- FINANCE-IMP-001 — Finance/Treasury Ledger, Settlement & Inventory Valuation Authority Tranche
- status: READY FOR IMPLEMENTATION / SCOPE FROZEN
- canonical readiness: docs/plan/09-finans-kasa-banka/p5-finance-treasury-readiness.md
- scope-freeze baseline: 35eb688cc2f96d5128c24dc14fe4e6d040d28c34
- migration baseline: 13

Repository reconciliation:
- Warehouse runtime-tested source position remains 81efd9c347118db24d8945f4d49e63bf99baaf70;
- all commits between that tested source position and scope-freeze baseline are documentation-only;
- no source/migration drift was found;
- latest relevant Warehouse Build 36068722249 and Test Deploy 36068722239 are SUCCESS.

Frozen broad authority:
- Account/Cash/Bank/Inventory Valuation ledgers;
- Finance transactions, settlement/advances/refunds/role netting;
- posting periods and immutable reversal;
- same-currency treasury;
- statement evidence/reconciliation;
- customer risk/credit/hold;
- Goods Receipt provisional valuation;
- Dispatch value-out + dispatched-not-invoiced bridge;
- Sales Invoice AR + COGS POST/REVERSE;
- Supplier Invoice AP + late price-cost delta POST/REVERSE;
- Count/Scrap valuation integration;
- protected Finance API and Mars.Web.

Current repository gate:
- Company base-currency/rate authority is absent;
- authoritative FINANCE-IMP-001 posting is TRY-only;
- non-TRY/FX transfer/realized FX/unrealized revaluation remains fail-closed rather than fabricated.

Next action:
- continue implementation on main inside FINANCE-IMP-001;
- first Finance migration is additive from baseline 13;
- run normal targeted build/test/migration/API smoke only;
- do not run production deployment or Full Test Day.

