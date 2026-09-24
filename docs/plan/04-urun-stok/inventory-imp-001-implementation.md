# INVENTORY-IMP-001 — Inventory Authority & Traceability Tranche Implementation

Status: COMPLETED

## Scope

INVENTORY-IMP-001 implements the broad Inventory authority and traceability substrate frozen in:

- docs/plan/04-urun-stok/p5-inventory-authority-traceability-readiness.md

The implementation preserves:
- Inventory Ledger as authoritative physical quantity truth;
- Reservation as separate non-physical commitment authority;
- Location and disposition as separate dimensions;
- Lot/Serial quantity and position as ledger-derived;
- Warehouse operational workflows as separate later authority;
- Finance/Costing valuation/current-cost/COGS as separate authority.

No generic public arbitrary stock-post endpoint was introduced.
No free-form public Reservation mutation disconnected from a Sales Order source was introduced.

## Repository evidence

Scope-freeze baseline:
- f6a15146b68f1ec6016c14437ad7d11159ea1a7f

Readiness/state baseline:
- 6ee2dd0381316d2b1a3039fea54a080107242e73

Implementation sequence begins with:
- fcafae3e28c2d347f27f6dfa4379033f8e66991a — Inventory authority domain model

Key implementation commits include:
- 4edd7c16afdccd401b4fd9db3c1b16066d86621f — permission contract
- f3a272676bd30f4eeb6950a653d7ccfbe4514d5c — application authority contracts
- f0dd2864aab77e2fc1f0944bbdaf9f7dd2f2fa9b — persistence records
- cdb7d6c31b71fd8c8b9c49f3c5545ea75cf850ef — normalized Inventory EF model
- 598222a47c0a4b1f242f8795a5458513572f2f37 — UOM conversion snapshot handling
- 88304bfd3a6db75394ee45604a6240868a805222 — base-quantity multiplication scale
- ac14c4588fa2cb7d1735e1b856804b7abb7f390f — Inventory authority persistence
- c2c447d2cfe54e55e6c4808708abdec77735054e — stock and trace reads
- f9d386e359209493363cb1a899fdffdd6d624e70 — EF model registration
- f6d13d41f56e0f386a47959306b8e58af819b813 — API requests
- 28c8aada5a4d127af5d10a45589b2a6004bf4707 — protected Inventory master/read API
- 09185aa0ca948acf5f298bd1d906b8116882b51d — service/API wiring
- 07a279e12fa76e2eea42fc34c853e4a666526f93 — targeted Inventory invariants
- 3e68650fd1a8bb109e3833e9f131bb3a8bbedefa — Inventory Web workspace
- 15fbd0fb142e8b73b8ae52501cd59d0fad250da7 — Inventory navigation
- 53d9e815e4c4d09f6bec3bfaf788195beb6a843c — Inventory Web route
- 40fcd15ce794a95881e3e101ba03448fb497fb2a — Inventory Web authority tests
- c5ceb91f1d84f3b0be4919da3c587d5630ad555e — TEST smoke expansion
- 114c1208c6f23f5f01191d01c5593ca5a8fac055 — Inventory API smoke
- edcc24f1200b70aad102fc510ad7bec60c4515e7 — final tested position-resolution refactor

Generated Inventory migration:
- f4dc4cdf60bff8b5845347b6c50fbac5d64e3206
- src/Mars.Infrastructure/Persistence/Migrations/Inventory/20260924121439_InventoryImp001AuthorityTraceability.cs

Migration generator:
- workflow: INVENTORY-IMP-001 Migration Generate
- run: 35997791784
- result: SUCCESS
- generator later frozen as manual/read-only evidence tooling by 4d2e2612f5f2d87a6ce801f92dd3ced096ce1c43

Final runtime-tested SHA:
- edcc24f1200b70aad102fc510ad7bec60c4515e7

## Implemented authority

### Warehouse

Implemented:
- company-scoped public identity;
- code/name;
- ACTIVE/INACTIVE lifecycle;
- create/read/edit/state mutation;
- optimistic version;
- audit;
- durable idempotency;
- deactivation blocking against current physical quantity and active Reservation authority;
- no mutable Warehouse stock total.

Permissions:
- warehouse.read
- warehouse.manage
- warehouse.deactivate

### Location

Implemented:
- Warehouse ownership;
- optional same-Warehouse parent;
- hierarchy constraints;
- stock-bearing flag;
- ACTIVE/INACTIVE lifecycle;
- create/read/edit/state mutation;
- optimistic version;
- company/Warehouse compatibility;
- no mutable Location stock total.

Permissions:
- warehouse.location.read
- warehouse.location.manage
- warehouse.location.deactivate

### Physical disposition

Frozen controlled values are persisted:
- AVAILABLE
- QUARANTINE
- QUALITY_HOLD
- REWORK
- DAMAGED
- TRANSIT

RESERVED is not a physical disposition.

### Lot / Serial

Implemented:
- company/Product/Variant-scoped trace identities;
- Lot manufacture/expiry metadata;
- deterministic uniqueness;
- Serial optional Lot relation;
- ledger-derived physical state/position;
- metadata separated from quantity movement authority.

### Inventory Ledger

Implemented append-oriented Inventory Movement authority with:
- Product/Variant/UOM;
- entered quantity;
- conversion snapshot;
- base-normalized quantity;
- source/target Warehouse;
- source/target Location;
- source/target disposition;
- Lot/Serial;
- owning source identity;
- posting timestamp;
- actor/correlation;
- explicit reversal link.

Normal source-side posting revalidates physical quantity.
Posted history is not corrected by direct row mutation.

Quantity storage:
- entered quantity: numeric(28,9)
- conversion snapshot: numeric(28,9)
- base quantity: numeric(38,18)

This storage precision does not define a new per-UOM business fraction policy.

### Reservation

Implemented separate Inventory-owned Reservation authority:
- exact Sales Order public id/version/line source contract;
- Product/Variant;
- Warehouse;
- UOM/conversion snapshot;
- append-oriented Create/Increase/Release/Consume history;
- derived current quantity;
- available-to-reserve revalidation;
- durable transaction/idempotency integration.

Reservation remains non-physical.

No public free-form Reservation mutation endpoint is exposed before Sales owns the initiating workflow.

### Read surfaces

Implemented protected reads for:
- Warehouses;
- Locations;
- stock summary;
- physical positions;
- movement history;
- Lot trace;
- Serial trace/current position;
- Reservation history/current remainder.

Stock remains projection/read truth derived from Inventory Ledger and Reservation records.

### API / Mars.Web

Protected /api/v1/inventory surface includes master and read operations for the accepted public scope.

Mars.Web:
- /inventory route;
- Warehouse/Location management;
- stock/position/movement/trace views;
- no editable stock-total field;
- no valuation/current-cost/COGS authority;
- no generic arbitrary posting UI.

## Persistence

Schema:
- inventory

Committed structures:
- inventory.warehouses
- inventory.locations
- inventory.dispositions
- inventory.lots
- inventory.serials
- inventory.movements
- inventory.reservations
- inventory.reservation_movements

Committed migration count after Inventory:
- 8

TEST migration safety now includes:
- Foundation
- Identity
- Parties
- Products
- Inventory

EF pending-model result on final tested SHA:
- PASS
- no changes since last migration

## Verification

### Foundation Build

Run:
- 35998315605

Job:
- 107628488066

SHA:
- edcc24f1200b70aad102fc510ad7bec60c4515e7

Result:
- SUCCESS

Evidence:
- frontend verify: 20 / 20 PASS
- Foundation targeted tests: 74 PASS
- Inventory targeted tests included 7 explicit INVENTORY-IMP-001 cases
- Release build: 0 warnings / 0 errors
- EF pending-model: PASS
- Inventory protected API smoke: 401 unauthenticated
- OpenAPI includes Inventory routes

### Foundation Test Deploy

Run:
- 35998315582

Job:
- 107628488098

SHA:
- edcc24f1200b70aad102fc510ad7bec60c4515e7

Result:
- SUCCESS

Evidence:
- frontend verify: 20 / 20 PASS
- Foundation targeted tests: 74 PASS
- migration safety: 8 committed migrations checked
- EF pending-model: PASS
- migrator build: PASS
- web build: PASS
- deployed EF migration count: 8
- deployment result: PASS
- /products = 200
- /parties = 200
- /inventory = 200
- /health/live = 200
- /health/ready = 200
- Inventory protected routes unauthenticated = 401
- OpenAPI = 200
- runner-to-TEST smoke = PASS

No real authenticated TEST Inventory mutation is claimed.

## Explicit exclusions / deferred

Not implemented by INVENTORY-IMP-001:
- Sales Order / Dispatch workflow;
- Purchase Order / Goods Receipt workflow;
- Returns workflow;
- Warehouse receiving work;
- put-away workflow;
- pick/pack/stage/load;
- Warehouse Transfer state machine;
- Stock Count state machine;
- replenishment;
- damage/scrap request/approval workflow;
- offline/mobile Warehouse work;
- arbitrary public manual physical posting;
- arbitrary user-created physical disposition;
- Finance valuation/current cost/moving average/cost layers/COGS;
- Product Base UOM replacement;
- post-use STOCKABLE transition;
- post-use tracking transition;
- generic Variant EAV;
- provider/marketplace/GS1 behavior;
- production deployment;
- Full Test Day.

Heavy concurrency, authenticated permission/IDOR matrix, browser E2E, high-volume stock reads, load/performance, backup/restore and broad security/ledger regression remain Full Test Day work.

## Completion decision

INVENTORY-IMP-001 is COMPLETED.

The next P5 dependency is Sales broad implementation tranche definition.

No Sales implementation package ID is assigned by this completion record.
