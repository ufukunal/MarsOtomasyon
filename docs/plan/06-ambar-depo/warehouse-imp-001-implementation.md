# WAREHOUSE-IMP-001 — Warehouse Execution & Inventory Control Authority Tranche

Status: COMPLETED / RUNTIME VERIFIED

## Package identity

- Work package: WAREHOUSE-IMP-001
- Module: Warehouse
- Phase: P5 — Core application implementation
- Canonical readiness: docs/plan/06-ambar-depo/p5-warehouse-execution-readiness.md
- Runtime-tested implementation commit: 81efd9c347118db24d8945f4d49e63bf99baaf70
- Predecessor: PURCHASING-IMP-001 — COMPLETED

WAREHOUSE-IMP-001 was implemented as one broad coherent tranche. No Warehouse micro-package IDs were introduced.

## Authority boundaries preserved

- Inventory Ledger remains authoritative physical quantity truth.
- Reservation remains a separate non-physical commitment.
- Warehouse owns operational execution/work evidence, not a duplicate stock ledger.
- Sales owns Dispatch POST and outbound STOCK OUT.
- Purchasing owns Goods Receipt POST and inbound STOCK IN to QUARANTINE.
- Pick / Pack / Stage / Load have STOCK = NONE.
- Finance owns valuation, COGS, account posting and scrap/write-off accounting.
- Positive Stock Count adjustment remains fail-closed until Finance/Costing valuation authority exists.
- Quality inspection-plan/result authority was not implemented.
- No generic negative-stock override was introduced.
- Production deployment and Full Test Day were not run.

## Implemented authority

### Permissions and scope

PLAN-006 Warehouse permissions were added for:
- Warehouse/Location operations;
- receiving/disposition/put-away;
- pick strategy/read/execute/override;
- pack/stage/load;
- transfer create/issue/receive/reconcile/loss/reverse;
- count create/execute/review/approve/post/reverse;
- damage/scrap;
- trace reads.

Warehouse state-changing operations require the existing Company scope plus Warehouse access where applicable.

Inventory master deactivation now consumes an Inventory-owned operational-blocker contract implemented by Warehouse. Warehouse/Location INACTIVE is blocked while relevant Warehouse work remains open; historical closed work does not permanently block deactivation.

### Receiving / quarantine / disposition

Warehouse exposes a rebuildable receiving queue sourced from Purchasing-owned POSTED Goods Receipt lineage.

Stockable receipt physical STOCK IN remains Purchasing-owned and enters QUARANTINE. Warehouse does not expose a Goods Receipt POST action.

Disposition/release commands delegate exact physical effects to IInventoryPhysicalAuthority.

Damage recording is an authorized physical disposition movement to DAMAGED or QUALITY_HOLD and does not create or destroy company quantity.

### Put-away and replenishment

Put-away and manual replenishment use Inventory physical authority for internal position movements.

Invariant:
- same Company/Warehouse;
- exact Product/Variant/UOM/Lot/Serial lineage;
- disposition preserved;
- Location changes;
- replenishment source must be AVAILABLE;
- company net physical quantity = 0.

### Pick / FEFO / FIFO

Pick work is linked to Sales Dispatch.

Implemented:
- ACTIVE STOCKABLE Product validation;
- ACTIVE stock-bearing Location validation;
- AVAILABLE-only normal pick;
- exact Lot/Serial validation;
- expired Lot normal-pick rejection;
- deterministic FEFO recommendation for expiry-tracked stock;
- FIFO recommendation otherwise;
- controlled strategy override requiring permission and reason;
- override cannot bypass eligibility or physical availability;
- cumulative pick cap against Dispatch line;
- Warehouse access scope.

Pick itself creates no Inventory movement.

### Sales-owned Dispatch source binding

Warehouse never mutates Sales persistence directly.

A narrow Sales-owned pre-POST source-binding contract accepts exact Warehouse pick-selected:
- Warehouse;
- Location;
- Lot;
- Serial;
- picked quantity;
- Dispatch/version/line identity;
- operation key.

To support legitimate multi-source picking on one Dispatch line, Sales owns normalized append-oriented:
- sales.dispatch_source_allocations

Dispatch POST validates that Warehouse source allocations exactly cover the commercial Dispatch line when allocations exist.

Sales Dispatch POST then creates one Inventory physical STOCK OUT per exact allocation and consumes the linked Reservation quantity in the same PostgreSQL transaction.

Physical source identity is allocation-specific, so retries and reversal lineage remain deterministic.

Dispatch reversal compensates each original physical effect individually and preserves posted history.

Warehouse still exposes no Dispatch POST route.

### Package / pack / stage / load

Implemented normalized:
- Package;
- Package Item;
- Stage/Load work;
- Stage/Load-to-Package relation.

Cumulative packed quantity cannot exceed picked quantity.

Pack / Stage / Load have no Inventory movement and no Sales commercial posting authority.

### Warehouse Transfer

Warehouse Transfer states implemented:
- DRAFT
- ISSUED
- PARTIALLY_RECEIVED
- RECEIVED
- RECONCILIATION_REQUIRED
- CLOSED
- CANCELLED
- REVERSED

ISSUE:
- source AVAILABLE -> target Warehouse / null Location / TRANSIT;
- Inventory physical authority;
- company quantity conserved.

RECEIVE:
- TRANSIT -> target stock-bearing Location;
- AVAILABLE by default;
- explicit DAMAGED or QUALITY_HOLD receive exception supported;
- partial receive supported;
- unresolved quantity remains TRANSIT.

Transit loss:
- exact unresolved quantity only;
- mandatory reason;
- approval evidence and SoD;
- approved source-only Inventory movement;
- no Finance posting.

Transfer close requires zero unresolved TRANSIT.

Transfer reversal uses compensating Inventory movement(s) and does not delete posted history.

### Stock Count

Implemented states:
- DRAFT
- COUNTING
- REVIEW/PENDING_APPROVAL
- APPROVED
- POSTED
- CLOSED
- REVERSED

At COUNTING start:
- explicit normalized Location scope;
- deterministic Inventory movement boundary captured;
- expected-start quantity materialized per counted physical identity.

Count observations are append-oriented evidence; recount creates a new observation.

Review:
- net intervening movement after snapshot;
- expected reconciliation = expected start + intervening movement;
- discrepancy = accepted count - expected reconciliation.

SoD:
- an actor who performed the physical count cannot approve the same non-zero count adjustment.

Posting:
- negative discrepancy posts exact source-only Inventory adjustment;
- positive discrepancy returns valuation-required fail-closed outcome;
- no direct stock overwrite exists.

Posted negative count effects can be compensated by explicit count reversal.

### Damage / scrap

Scrap workflow implements:
- request;
- approval;
- SoD through shared approval authority;
- exact eligible non-available source position;
- Inventory source-only physical OUT;
- immutable effect evidence.

Finance write-off/value removal is not implemented by Warehouse.

### Offline operation journal

A server-side Warehouse operation journal records:
- Company;
- client_operation_id;
- actor;
- Warehouse;
- operation type;
- work reference;
- expected version;
- scan identity;
- local timestamp;
- result/conflict state;
- correlation.

Company + client_operation_id is unique for deterministic retry identity.

Device/mobile/Desktop shell and scanner-driver implementation remain excluded.

## Persistence

Warehouse schema uses normalized BIGINT internal keys, UUID public ids, explicit Company scope and Inventory-compatible decimal quantity precision.

WAREHOUSE-IMP-001 Warehouse structures include:
- warehouse.operations
- warehouse.disposition_effects
- warehouse.pick_works
- warehouse.packages
- warehouse.package_items
- warehouse.stage_load_works
- warehouse.stage_load_packages
- warehouse.transfers
- warehouse.transfer_lines
- warehouse.transfer_effect_links
- warehouse.stock_count_sessions
- warehouse.stock_count_scopes
- warehouse.stock_count_lines
- warehouse.stock_count_observations
- warehouse.stock_count_effect_links
- warehouse.scrap_requests
- warehouse.offline_operations

Cross-module Sales-owned support:
- sales.dispatch_source_allocations

No Warehouse table is authoritative mutable stock balance truth.

## Migrations

Pre-Warehouse migration baseline:
- 11

Warehouse authority migration:
- 20260924220511_WarehouseImp001ExecutionInventoryControlAuthority

Sales-owned source-allocation migration:
- 20260924223900_WarehouseImp001SalesDispatchSourceAllocations

Final committed/deployed migration count:
- 13

Both new migrations are additive in Up().

EF pending-model verification:
- PASS
- no model changes pending at final tested commit.

Migration generation workflows were converted to manual/read-only verification after committed migrations were produced.

## API and Web

Protected Warehouse API includes read/mutation surfaces for:
- receiving;
- reservations;
- dispositions;
- damage;
- put-away;
- replenishment;
- pick/recommendation;
- packages;
- staging/loading;
- transfers;
- counts;
- scrap;
- offline operations;
- trace.

Mars.Web:
- /warehouse

The Warehouse Web workspace exposes receiving/quarantine, disposition/put-away, Reservation visibility, picking, packaging/stage/load, transfers, counts, damage/scrap, trace and offline journal workflows.

The UI does not expose stock overwrite, Warehouse Dispatch POST or Warehouse Goods Receipt POST authority.

## Final runtime evidence

### Foundation Build

Run:
- 36068722249

Job:
- 107864429802

Result:
- SUCCESS

Evidence:
- frontend 23 / 23 PASS;
- Release build PASS;
- 0 warnings;
- 0 errors;
- Foundation targeted tests 108 / 108 PASS;
- WAREHOUSE multi-source pick -> multiple Sales physical effects PASS;
- Warehouse model contains no mutable stock balance authority PASS;
- put-away internal Inventory movement PASS;
- Warehouse scope pick guard PASS;
- Pick STOCK=NONE / Sales source-binding PASS;
- Transfer AVAILABLE -> TRANSIT composition PASS;
- positive Count adjustment valuation-required fail-closed PASS;
- damage eligibility guard PASS;
- EF pending-model clean;
- protected Warehouse API unauthenticated = 401;
- expected Warehouse OpenAPI surface present;
- Warehouse-owned Dispatch POST / Goods Receipt POST absent.

### Foundation Test Deploy

Run:
- 36068722239

Job:
- 107864430160

Result:
- SUCCESS

Evidence:
- migration safety PASS;
- PASS_MIGRATION_UP_COUNT = 13;
- EF pending-model clean;
- TEST preflight PASS;
- migration 20260924223900_WarehouseImp001SalesDispatchSourceAllocations applied;
- deployed migration count = 13;
- /warehouse = 200;
- /sales = 200;
- /purchasing = 200;
- /inventory = 200;
- /products = 200;
- /parties = 200;
- health/live = 200;
- health/ready = 200;
- protected Warehouse representative endpoints unauthenticated = 401;
- OpenAPI = 200;
- OpenAPI Warehouse surface = EXPECTED;
- OpenAPI Warehouse commercial POST authority = ABSENT;
- runner-to-TEST smoke PASS.

No real authenticated TEST Warehouse mutation is claimed.

## Deferred

Still deferred by frozen scope/policy:
- Finance Account/Cash/Bank/Valuation authority;
- positive Count Adjustment posting until valuation authority exists;
- inventory valuation/current cost/COGS/write-off accounting;
- Quality inspection-plan/result implementation;
- carrier/label/tracking provider;
- robotics/WMS provider;
- automatic replenishment optimizer/MRP;
- Device Layer/mobile/Desktop shell/scanner driver;
- capacity-policy administration not present in repository;
- production deployment;
- Full Test Day heavy concurrency, broad auth/IDOR, multi-device reordering, full browser/mobile E2E, load/performance, backup/restore and full security regression.

## Closure

WAREHOUSE-IMP-001 is complete and runtime-verified.

Next P5 action:
- define the Finance / Treasury broad implementation tranche from frozen PLAN-007;
- reconcile current repository authority first;
- do not assign a Finance implementation work-package id until the exact broad scope is frozen.
