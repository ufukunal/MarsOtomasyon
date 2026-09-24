# INVENTORY-IMP-001 — Inventory Authority & Traceability Tranche Readiness

Status: READY FOR IMPLEMENTATION

## Owner direction

The owner requires broad coherent implementation tranches rather than one work package per small Inventory capability.

INVENTORY-IMP-001 therefore groups the source-backed Inventory authority needed by later Sales, Purchasing, Warehouse and Returns implementation into one package. Internal sequencing and multiple commits are allowed. Do not split Warehouse master, Location master, physical disposition, Inventory Ledger, Reservation authority, Lot/Serial traceability and stock inquiry into separate INVENTORY-IMP package IDs merely for implementation convenience.

This package does not absorb Warehouse operational workflows or Finance/Costing valuation authority.

## Reconciled repository baseline

Repository: ufukunal/MarsOtomasyon
Branch: main
Scope-freeze HEAD: f6a15146b68f1ec6016c14437ad7d11159ea1a7f

Predecessor:
- PRODUCT-IMP-001 — COMPLETED
- canonical report: docs/plan/04-urun-stok/product-imp-001-implementation.md
- implementation commit: 817dfea81acbf1e93586b030412bd492f8a3ba20
- migration commit: 9b0731792a8641b632c612b7abb6f74799dd2aa4
- final runtime-tested commit: ac92911a8f5fcda070622b084216a43a70ad7d77
- Foundation Build 35981641268 — SUCCESS
- Foundation Test Deploy 35981641137 — SUCCESS
- committed migration count: 7

Current source baseline at scope freeze:
- no Inventory/Warehouse/Location/Reservation/Lot/Serial Domain implementation;
- no Inventory Application implementation;
- no Inventory persistence/configuration;
- no Inventory API endpoints;
- no Inventory Mars.Web route;
- no Migrations/Inventory directory;
- MarsDbContext currently applies Foundation, Party and Product configurations only;
- TEST migration safety currently scans Foundation, Identity, Parties and Products only.

## Sources

Governance:
- docs/plan/ai-cmd.md
- docs/ai/README.md
- docs/ai/skill-router.md
- docs/plan/active-task.yaml
- docs/plan/project-state.yaml
- docs/plan/handoff/current.md
- docs/plan/tasks/active.md
- docs/plan/tasks/backlog.md
- docs/plan/tasks/completed.md
- docs/plan/master-project-plan.md

Frozen Product / Inventory:
- docs/plan/04-urun-stok/README.md
- docs/plan/04-urun-stok/plan.md
- docs/plan/04-urun-stok/workflows.md
- docs/plan/04-urun-stok/forms.md
- docs/plan/04-urun-stok/data-contract.md
- docs/plan/04-urun-stok/permissions.md
- docs/plan/04-urun-stok/reports.md
- docs/plan/04-urun-stok/acceptance-criteria.md

Frozen Sales Reservation boundary:
- docs/plan/05-satis/plan.md
- docs/plan/05-satis/workflows.md
- docs/plan/05-satis/data-contract.md

Frozen Warehouse boundary:
- docs/plan/06-ambar-depo/plan.md
- docs/plan/06-ambar-depo/workflows.md
- docs/plan/06-ambar-depo/data-contract.md
- docs/plan/06-ambar-depo/permissions.md

Logical database:
- docs/db/00-domain-dictionary.md
- docs/db/01-design-principles.md
- docs/db/02-module-ownership.md
- docs/db/03-entity-catalog.md
- docs/db/04-relationships.md
- docs/db/05-ledgers-and-finance.md
- docs/db/06-snapshots-and-projections.md
- docs/db/07-constraints-and-concurrency.md
- docs/db/08-index-access-patterns.md
- docs/db/09-migration-conventions.md
- docs/db/acceptance-criteria.md

Current implementation / CI:
- src/Mars.Infrastructure/Persistence/MarsDbContext.cs
- .github/workflows/foundation-build.yml
- .github/workflows/test-deploy.yml
- .github/workflows/product-migration-generate.yml

## ACTIVE SKILLS

Primary:
- warehouse-operations-shipping-specialist — protects physical quantity, location/disposition, traceability, negative-stock and future Warehouse workflow boundaries.
- erp-domain-specialist — protects STOCK/RES recognition, source/reversal semantics and cross-module authority.
- database-architect — owns normalized ledger/reservation/trace schema, constraints, access patterns, migration and durable concurrency.

Reviewers:
- software-architect — modular-monolith boundaries, transaction contracts and no duplicate authority.
- software-developer — implementation consistency, server-side invariants and existing Foundation/Product reuse.
- software-test-engineer — targeted authority, duplicate, stale, idempotency, company and tracking evidence.
- accounting-finance-specialist — vetoes valuation/current-cost/COGS leakage into Inventory quantity authority.
- security-specialist — server-side permission and company isolation.
- ux-ui-specialist — stock inquiry, Warehouse/Location and traceability task flow.
- web-design-specialist — Mars.Web/Mars.UI continuity and accessible responsive implementation.

## Objective

Deliver the Inventory authority and traceability substrate that later commercial and Warehouse workflows can post into atomically, while also providing usable Warehouse/Location master management and read-only stock/trace surfaces.

The tranche must establish one physical quantity truth only:
posted Inventory Ledger history.

Reservation remains a separate non-physical commitment authority.

## Frozen authority model

Inventory owns:
- Warehouse master;
- Location master;
- controlled physical disposition identities;
- Lot identity and metadata;
- Serial identity and trace lineage;
- Reservation authority;
- append-oriented Inventory Ledger movement authority;
- rebuildable stock/availability/trace projections and queries.

Product remains authoritative for:
- Product/Variant identity;
- STOCKABLE capability;
- Base/alternate Product-UOM;
- tracking strategy;
- barcode/product master semantics.

Warehouse later owns operational workflows:
- receiving work;
- put-away execution;
- picking;
- packing;
- staging;
- loading;
- transfers and reconciliation;
- stock counts and observations;
- replenishment;
- operational damage/scrap requests and approvals;
- offline/mobile work execution.

Sales/Purchasing/Returns remain owners of their commercial/source documents and posting commands.

Finance/Costing remains authoritative for:
- inventory valuation;
- moving-average/current carrying value;
- cost layers/value effects;
- COGS;
- financial count/scrap/return valuation effects.

## Included capability set

### 1. Warehouse master

Implement company-scoped Warehouse master:
- public UUID;
- caller-supplied code;
- name;
- ACTIVE / INACTIVE;
- optimistic version;
- create/list/detail/edit/deactivate/reactivate;
- audit and durable idempotency for mutations.

Rules:
- code is deterministic in company scope;
- Warehouse stores no authoritative stock total;
- lifecycle never deletes movement history;
- final deactivation is blocked when current Inventory authority proves non-zero on-hand, active Reservation or unresolved TRANSIT quantity;
- future Warehouse work blockers are enforced when those work authorities exist; INVENTORY-IMP-001 does not invent placeholder work records.

Use the later, more-specific PLAN-006 permission namespace for Warehouse master:
- warehouse.read
- warehouse.manage
- warehouse.deactivate

### 2. Location master

Implement Location under exactly one Warehouse:
- public UUID;
- code;
- name;
- optional parent Location in the same Warehouse;
- stock-bearing/selectable flag;
- ACTIVE / INACTIVE;
- optimistic version;
- create/list/detail/edit/deactivate/reactivate;
- hierarchy cycle prevention;
- audit and durable idempotency.

Rules:
- aggregate/non-stock-bearing parent Locations never become quantity authority;
- no current_stock column;
- final deactivation is blocked by non-zero physical quantity or active Reservation scope that depends on the Location where such exact dependency exists;
- open Warehouse work blockers are evaluated later when Warehouse work records exist.

Permissions:
- warehouse.location.read
- warehouse.location.manage
- warehouse.location.deactivate

### 3. Controlled physical dispositions

Provide the frozen physical disposition set:
- AVAILABLE
- QUARANTINE
- QUALITY_HOLD
- REWORK
- DAMAGED
- TRANSIT

The physical representation must be controlled and relationally referenceable. These codes are not arbitrary user-created stock statuses in this tranche.

Rules:
- RESERVED is forbidden as a physical disposition;
- Reservation remains a separate authority;
- only AVAILABLE contributes to normal available physical quantity;
- non-AVAILABLE physical dispositions still contribute to on-hand where physically owned;
- TRANSIT remains physical company-owned quantity but is not normal source-Warehouse AVAILABLE stock;
- disposition change is a physical ledger effect, not a master edit.

Read permission follows warehouse.disposition.read where exposed.

### 4. Lot traceability identity

Implement Lot identity for LOT / LOT_SERIAL tracked stockable trade identities:
- public UUID;
- company;
- exact Product and optional Variant trade identity;
- lot code;
- optional manufacture date;
- optional expiry date;
- metadata version/audit where mutable metadata is supported.

Rules:
- lot code is unambiguous within company + Product/Variant lot scope;
- Lot quantity is never stored as mutable authority;
- physical quantity/location/disposition is ledger-derived;
- metadata edit cannot move stock or rewrite posted history;
- expiry affects later Warehouse eligibility according to frozen PLAN-006 rules.

Physical introduction of a Lot occurs through an accepted Inventory posting contract. Do not add a free-form stock-creating Lot UI.

### 5. Serial traceability identity

Implement Serial identity for SERIAL / LOT_SERIAL tracked stockable trade identities:
- public UUID;
- company;
- exact Product and optional Variant trade identity;
- serial value;
- optional Lot;
- metadata/audit where source-backed.

Rules:
- serial is unambiguous within company + Product/Variant serial scope;
- current Warehouse/Location/disposition is reconstructed from ledger history/projection;
- one serial instance cannot have two simultaneous authoritative positions;
- each physical serial movement is unitary in Base UOM;
- fractional serial movement is invalid;
- metadata edit cannot relocate a serial;
- correction/reversal creates new history.

### 6. Authoritative Inventory Ledger

Implement an append-oriented Inventory Movement authority.

Each posted movement preserves applicable:
- public UUID and internal identity;
- company;
- Product and optional Variant;
- entered UOM;
- entered quantity;
- accepted Product-UOM conversion snapshot;
- base-normalized quantity;
- source Warehouse/Location/disposition;
- target Warehouse/Location/disposition;
- Lot;
- Serial;
- exact source module/document/work/line identity supplied by the owning workflow;
- posting timestamp;
- actor;
- correlation/idempotency identity;
- original/reversal linkage.

Rules:
- posted movement quantity is positive; source/target semantics determine physical direction/effect;
- at least one physical side exists;
- internal location/disposition movement preserves company total quantity;
- no update/delete correction of posted history;
- reversal/compensation is a new movement linked to the original;
- no Product/Warehouse/Location mutable stock total becomes authority;
- normal posting cannot create negative physical stock at the required physical scope;
- tracking strategy is enforced from Product/Variant authority;
- NONE forbids invented lot/serial requirements;
- LOT requires Lot;
- SERIAL requires Serial;
- LOT_SERIAL requires both compatible Lot and Serial;
- inactive Product/Variant may still be referenced by authorized cleanup/reversal paths when the owning workflow allows it;
- live Product/UOM changes never reinterpret stored movement snapshots.

INVENTORY-IMP-001 must not expose a public generic "post arbitrary movement" endpoint or UI. Physical posting is an internal Inventory application contract used atomically by the owning Sales/Purchasing/Warehouse/Returns commands as those modules are implemented.

Do not create catch-all MANUAL movement semantics. An owning workflow must supply a source-backed physical effect contract.

### 7. Reservation authority

Implement Inventory-owned Reservation as a non-physical authority tied to an exact effective Sales Order line/version source contract.

Preserve:
- public UUID;
- company;
- exact Sales source identity/version/line identity;
- Product and optional Variant;
- Warehouse scope;
- entered UOM and accepted conversion snapshot;
- base-normalized quantity;
- append-oriented Reservation create/increase/release/consume lineage;
- actor/correlation/idempotency;
- original/reversal/compensation relation where applicable.

Rules:
- Sales Order confirmation never auto-reserves;
- explicit authorized Sales action initiates Reservation;
- Reservation changes RES only, never physical STOCK;
- active reserved quantity is derived from authoritative Reservation history, not copied into Sales or Product;
- normal Reservation cannot exceed current eligible AVAILABLE-to-reserve quantity;
- Dispatch later consumes/releases related Reservation atomically with its physical POST;
- release racing Dispatch returns a conflict rather than silently overwriting history;
- Reservation is Warehouse-scoped, not silently converted into a physical Location/status allocation;
- RESERVED is not a physical status.

Because Sales implementation follows Inventory in P5, INVENTORY-IMP-001 must provide the internal authority/persistence contract but must not expose a free-form user Reservation create endpoint disconnected from a real Sales Order line/version. Reservation mutation becomes externally reachable through the later Sales workflow.

### 8. Durable concurrency / idempotency

Use PostgreSQL as the correctness authority.

Requirements:
- Warehouse/Location mutable masters use optimistic stale-write protection;
- externally retryable or cross-module Inventory posting operations use durable Foundation idempotency;
- Reservation mutations use durable idempotency;
- duplicate posting/reversal cannot produce duplicate physical effect;
- Reservation and physical posting revalidate current eligible quantity in the same authoritative transaction boundary required by the owning command;
- concurrent serial movement accepts only one valid next position;
- Valkey may reduce contention later but cannot be the sole lock/correctness mechanism.

The exact PostgreSQL locking/query mechanism is a database-architect implementation choice. It must prove the frozen outcomes and must not introduce a mutable stock-balance authority.

### 9. Stock / availability / trace read surfaces

Implement rebuildable/read-only Inventory queries for:
- on_hand;
- available_on_hand;
- reserved;
- available_to_reserve;
- Warehouse/Location/disposition balances;
- Lot balance/expiry trace;
- Serial current derived position and movement timeline;
- Inventory movement history;
- Reservation history/current derived remainder.

Definitions:
- on_hand = net posted physical Inventory Ledger effect;
- available_on_hand = net physical quantity in AVAILABLE;
- reserved = net active Reservation commitment;
- available_to_reserve = available_on_hand - reserved.

Required filters/grain where applicable:
- company;
- Product/Variant;
- Warehouse;
- Location;
- disposition;
- Lot;
- Serial;
- posting sequence/time;
- source identity.

A projection/cache may be introduced only as rebuildable acceleration. It cannot accept business posting as its own authority.

Permission:
- inventory.stock.read for quantity inquiry;
- inventory.trace.read for Inventory-owned trace read surfaces;
- inventory.lot.manage_metadata / inventory.serial.manage_metadata only for metadata operations that cannot move quantity.

PLAN-006 warehouse.trace.read remains the Warehouse operational trace permission and does not create a second trace authority.

### 10. API / Mars.Web

Protected API surface will cover the included public capabilities:
- Warehouse list/detail/master lifecycle;
- Location list/detail/hierarchy/master lifecycle;
- stock inquiry;
- movement history read;
- Lot/Serial trace read;
- allowed Lot/Serial metadata maintenance;
- Reservation read/history.

No public arbitrary physical-post or free-form Reservation mutation API is included.

Mars.Web adds an Inventory surface using existing Mars.UI primitives. A coherent route may group:
- stock inquiry;
- Warehouses / Locations;
- Lot / Serial trace;
- movement / Reservation history.

No "set stock = X" control is permitted.
No inventory valuation/current-cost/COGS field is presented as Inventory authority.

### 11. Database boundary

Expected normalized Inventory-owned physical structures:
- Warehouse;
- Location;
- controlled Inventory Disposition;
- Lot;
- Serial;
- Reservation;
- append-oriented Reservation movement/history;
- append-oriented Inventory Movement.

Physical schema:
- schema name: inventory;
- BIGINT internal primary keys;
- UUID public IDs where API/addressable;
- PostgreSQL authoritative;
- relational/3NF by default;
- same-company and Product/Variant compatibility enforced;
- no JSON/EAV escape for core relations;
- no authoritative mutable stock-total table/column.

Quantity/conversion storage uses .NET decimal / PostgreSQL NUMERIC. Exact physical precision/scale must be fixed by the database-architect before migration generation and must preserve deterministic Product-UOM conversion snapshots. That storage decision must not silently invent a per-UOM business fraction policy; the broader transaction UOM fraction/scale policy remains separately deferred.

Migration is additive and history-safe.

### 12. Permission / scope reconciliation

Repository permission precedence is reconciled as follows:
- PLAN-004 inventory.warehouse.* and inventory.location.* were explicitly proposed names;
- later frozen PLAN-006 defines warehouse.* and warehouse.location.* for Warehouse/Location;
- INVENTORY-IMP-001 uses the later PLAN-006 Warehouse/Location namespace;
- inventory.stock.read and Inventory-owned trace/metadata permissions remain valid from PLAN-004 because PLAN-006 does not replace the Inventory quantity authority.

Foundation Permission Grant remains Actor + trusted Company + PermissionCode.

INVENTORY-IMP-001 enforces:
- authenticated actor;
- authoritative ERP permission;
- trusted company;
- same-company Warehouse/Location/Product/Variant references;
- no cross-company IDOR.

The repository has not frozen a separate persisted Actor-to-Warehouse assignment administration model. This tranche does not invent one. Company-scoped master/read permissions are implemented now; a narrower actor-specific Warehouse assignment model, if required for later operational execution, must be added from an explicit source-backed Warehouse authorization contract.

## Explicit exclusions

INVENTORY-IMP-001 does NOT implement:
- Purchase Order or Goods Receipt documents/workflow;
- Sales Order or Dispatch documents/workflow;
- customer/supplier Returns workflow;
- receiving work;
- QC/Quality inspection workflow;
- put-away work/execution;
- picking, packing, staging or loading;
- Warehouse Transfer aggregate/state machine/issue/receive/reconcile UI;
- Stock Count session/observation/recount/approval workflow;
- replenishment workflow;
- damage/scrap request/approval workflow;
- offline/mobile scan queue/work execution;
- package/pallet/shipment topology;
- arbitrary manual physical movement posting;
- arbitrary user-created Inventory dispositions;
- mutable stock totals;
- inventory valuation/current cost/moving-average/cost layers/COGS;
- Product Base UOM replacement;
- post-use Product STOCKABLE transition;
- post-use tracking-strategy transition;
- generic Variant EAV;
- provider/marketplace synchronization or GS1 verification;
- production deployment;
- Full Test Day.

The excluded Warehouse workflows will later call the Inventory authority created here rather than create a second stock mechanism.

## Acceptance evidence

Normal development evidence for INVENTORY-IMP-001 must include:
- targeted Inventory domain/application/persistence tests;
- Warehouse/Location duplicate, hierarchy, lifecycle and stale-write tests;
- same-company/cross-company isolation;
- permission enforcement;
- fixed disposition semantics and RESERVED rejection;
- Lot/Serial uniqueness and tracking-strategy enforcement;
- serial unitary/no-double-position invariant;
- append-only movement/reversal behavior;
- no negative normal physical posting;
- Reservation non-physical behavior and available-to-reserve protection;
- durable posting/Reservation idempotency;
- stock formula/read-model tests;
- frontend verification;
- .NET Release build;
- additive generated Inventory migration;
- TEST migration-safety expansion to Migrations/Inventory;
- EF pending-model clean;
- TEST deployment and smoke;
- /inventory public web route returns 200 where implemented;
- /products and /parties regression smoke remains healthy;
- /health/live and /health/ready healthy;
- protected Inventory endpoints return 401 unauthenticated;
- OpenAPI contains the public Inventory surface.

Do not claim authenticated TEST Inventory mutation unless direct evidence exists.

Heavy PostgreSQL concurrency, broad authenticated permission/IDOR matrix, browser E2E, high-volume stock inquiry, performance/load, backup/restore and full ledger invariant regression remain Full Test Day.

## SOURCE / INFERENCE / UNKNOWN / BLOCKED

SOURCE:
- PLAN-004 freezes Inventory Ledger as physical quantity truth, Reservation as separate non-physical authority, Location/disposition separation, six physical dispositions and Lot/Serial traceability.
- PLAN-002 freezes explicit/manual Sales Reservation and exact effective Sales Order line/version linkage.
- PLAN-006 freezes normal negative-stock blocking, immutable/reversible physical history and Warehouse operational ownership.
- PLAN-010 freezes Inventory/Warehouse logical ownership, source lineage, constraints, access patterns and projection/authority separation.
- owner requires broad coherent implementation tranches.

INFERENCE / ordinary implementation decisions:
- one broad Inventory authority/traceability package is preferable to micro-packages because all included entities participate in the same physical quantity/eligibility invariants;
- Warehouse/Location permission names follow later PLAN-006 instead of earlier proposed PLAN-004 names;
- no public generic movement/Reservation mutation endpoint is created before an owning workflow exists;
- physical Inventory uses schema inventory;
- append-oriented Reservation history is used so current remaining never replaces historical authority.

UNKNOWN / deferred but non-blocking:
- exact per-UOM transaction quantity scale/fraction business policy;
- persisted actor-specific Warehouse assignment administration model;
- Warehouse work records and operational state machines;
- exact Finance valuation/cost behavior;
- Product high-risk Base UOM/STOCKABLE/tracking transition procedure after physical use.

BLOCKED:
- none for the frozen INVENTORY-IMP-001 scope.
- excluded semantics remain fail-closed until their owning package is implemented.

## Decision

Assigned:
INVENTORY-IMP-001 — Inventory Authority & Traceability Tranche

Status:
READY FOR IMPLEMENTATION
