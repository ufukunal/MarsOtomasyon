# P5 Warehouse Execution & Inventory Control Readiness

Status: READY FOR IMPLEMENTATION

Work package:
WAREHOUSE-IMP-001 — Warehouse Execution & Inventory Control Authority Tranche

## 1. Purpose

Implement PLAN-006 as one broad coherent Warehouse tranche over the already implemented Inventory, Sales and Purchasing authorities.

This tranche owns Warehouse operational execution/work state. It must not create a second physical stock truth and must not move Sales/Purchasing posting ownership.

## 2. Source basis

Repository authority:
- docs/plan/ai-cmd.md
- docs/ai/session-execution-protocol.md
- docs/ai/skill-router.md
- docs/plan/06-ambar-depo/plan.md
- docs/plan/06-ambar-depo/workflows.md
- docs/plan/06-ambar-depo/data-contract.md
- docs/plan/06-ambar-depo/forms.md
- docs/plan/06-ambar-depo/permissions.md
- docs/plan/06-ambar-depo/integrations.md
- docs/plan/06-ambar-depo/reports.md
- docs/plan/06-ambar-depo/acceptance-criteria.md
- current Inventory/Sales/Purchasing implementation

Implemented predecessor authority:
- Inventory Ledger and Reservation authority
- Warehouse/Location/Lot/Serial masters
- Warehouse Access Grant / IWarehouseAccessEvaluator
- IInventoryPhysicalAuthority
- Sales Dispatch commercial + physical POST authority
- Purchasing Goods Receipt commercial + physical POST authority

Migration baseline before Warehouse implementation:
11 committed/applied TEST migrations.

## 3. Active skill ownership

Primary:
- warehouse-operations-shipping-specialist — Warehouse execution semantics, scan validation, pick/pack/transfer/count/disposition.
- erp-domain-specialist — source-document, RES/STOCK effects, partial/reversal boundaries.

Reviewers:
- database-architect — normalized Warehouse work model, constraints, concurrency/idempotency and ledger links.
- accounting-finance-specialist — prevent Warehouse valuation/account postings and protect positive count valuation dependency.
- software-architect — Inventory composition and Sales/Purchasing module boundary.
- software-developer — implementation against existing contracts without duplicate stock mechanisms.
- software-test-engineer — fast invariant/transaction/API/Web evidence.
- security-specialist — company/Warehouse scope, privileged operations and SoD.
- ux-ui-specialist / web-design-specialist — scan-first /warehouse UI using Mars.UI.

## 4. Frozen authority boundaries

### Inventory

Inventory remains authoritative for:
- physical quantity;
- physical positions;
- dispositions;
- Lot/Serial position truth;
- Reservation truth;
- negative-stock protection.

Warehouse never persists authoritative mutable stock totals.

All Warehouse physical effects call the existing Inventory physical authority inside the same MarsDbContext transaction as Warehouse work-state completion.

### Sales

Sales remains owner of:
- Dispatch commercial document;
- Dispatch POST;
- authoritative outbound Sales STOCK OUT;
- Reservation consume/release during Dispatch POST;
- Dispatch reversal.

Warehouse owns:
- pick;
- package/pack;
- stage;
- load;
- pre-post operational readiness evidence.

Pick/pack/stage/load create STOCK = NONE.

Warehouse does not expose a second Dispatch POST endpoint.

Where Warehouse selection must bind exact source Location/Lot/Serial into a Sales Dispatch, implement a narrow Sales-owned pre-POST contract for Warehouse execution selection. Warehouse may call that contract; it must not write Sales tables directly.

### Purchasing

Purchasing remains owner of:
- Goods Receipt document;
- Goods Receipt POST;
- inbound STOCK IN;
- initial QUARANTINE position;
- Goods Receipt reversal.

Warehouse consumes POSTED Goods Receipt lineage for disposition/put-away work.

Put-away cannot repeat Goods Receipt STOCK IN.

### Quality

Quality inspection plan/result authority is outside this tranche.

Warehouse may execute an authorized physical disposition command. A future Quality result may become input to that command, but Warehouse must not fabricate Quality inspection/result truth.

### Finance

Warehouse posts no:
- Account Ledger;
- Cash/Bank;
- inventory valuation;
- COGS;
- write-off accounting.

Scrap physical OUT is Warehouse/Inventory quantity effect only.

Positive count adjustment POST is fail-closed in WAREHOUSE-IMP-001 until an implemented Finance/Costing valuation authority supplies an accepted valuation basis. Count workflow/review/approval still exists and may show POSITIVE_VALUATION_REQUIRED.

## 5. Included authority

### A. Warehouse permissions

Implement the frozen PLAN-006 permission namespace:

Master/read:
- warehouse.read
- warehouse.manage
- warehouse.deactivate
- warehouse.location.read
- warehouse.location.manage
- warehouse.location.deactivate

Inbound:
- warehouse.receiving.read
- warehouse.putaway.execute
- warehouse.disposition.read
- warehouse.disposition.release
- warehouse.disposition.change

Outbound work:
- warehouse.pick.read
- warehouse.pick.execute
- warehouse.pick.strategy_override
- warehouse.pack.execute
- warehouse.stage.execute
- warehouse.load.execute

Transfer:
- warehouse.transfer.read
- warehouse.transfer.create
- warehouse.transfer.issue
- warehouse.transfer.receive
- warehouse.transfer.reconcile
- warehouse.transfer.loss_adjust
- warehouse.transfer.reverse

Count:
- warehouse.count.read
- warehouse.count.create
- warehouse.count.execute
- warehouse.count.review
- warehouse.count.approve
- warehouse.count.post
- warehouse.count.reverse

Damage/scrap:
- warehouse.damage.record
- warehouse.scrap.request
- warehouse.scrap.approve
- warehouse.scrap.post

Trace:
- warehouse.trace.read

There is no warehouse.negative_stock_override.

All state-changing Warehouse commands require:
- required permission;
- trusted Company scope;
- exact relevant Warehouse Access Grant(s).

Transfer ISSUE requires source Warehouse access.
Transfer RECEIVE requires target Warehouse access.
Transfer create requires access to both source and target Warehouses as the fail-closed implementation rule.

### B. Receiving queue / receipt handoff

Provide read-only Warehouse receiving queue over Purchasing POSTED Goods Receipt lineage:
- source Receipt/PO;
- Product/Variant/UOM;
- Warehouse/Location;
- QUARANTINE quantity;
- lot/serial;
- receipt POST time;
- disposition/put-away remaining.

Warehouse must not create or POST Goods Receipt.

### C. Disposition

Warehouse physical disposition command:
- exact Product/Variant/UOM;
- Warehouse/Location;
- source disposition;
- target disposition;
- Lot/Serial;
- positive quantity;
- source work/Receipt lineage where available;
- reason where required;
- operation key.

Uses Inventory source -> target movement with same quantity and same physical identity.

Net company quantity = 0.

TRANSIT is not a generic manual disposition target/source for ordinary disposition commands; transfer owns TRANSIT movement.

Release is the explicit path to AVAILABLE.

Manual disposition command may target AVAILABLE, QUARANTINE, QUALITY_HOLD, REWORK or DAMAGED subject to source/target difference, eligibility, permission and exact current Inventory position. No Quality result is fabricated.

### D. Put-away

Put-away work:
- source posted Goods Receipt/disposition lineage;
- Product/Variant/UOM;
- source Warehouse/Location/disposition;
- target Location;
- lot/serial;
- requested/completed/remaining quantity;
- work state;
- actor/time/audit.

States:
OPEN -> ASSIGNED -> IN_PROGRESS -> PARTIALLY_COMPLETED -> COMPLETED
with pre-effect CANCELLED where eligible.

Completion:
- Inventory internal Location movement;
- disposition preserved;
- company/Warehouse net quantity 0;
- partial completion allowed;
- target must be ACTIVE and stock-bearing;
- exact Product/Lot/Serial source revalidated.

No capacity quantity/pallet/cube policy is invented because no authoritative capacity configuration exists.
When future capacity policy exists, it may add a blocking check without changing movement semantics.

### E. Manual replenishment

Include manual replenishment execution only:
- AVAILABLE source position;
- source/target Location in same Warehouse;
- Product/Variant/UOM/Lot/Serial;
- positive quantity;
- source and target revalidation;
- internal Inventory movement;
- net quantity 0.

Automatic reorder/replenishment thresholds, slotting and optimizer belong later MRP/Warehouse optimization and are excluded.

### F. Dispatch operational work

Warehouse pick work is linked to Sales Dispatch + exact Dispatch line.

Pick work states:
OPEN -> IN_PROGRESS -> PARTIALLY_PICKED -> PICKED -> CLOSED
and eligible pre-completion CANCELLED.

Pick validates:
- Dispatch is current and pre-POST;
- line remaining;
- Warehouse;
- Reservation remaining when linked;
- STOCKABLE Product;
- ACTIVE source Warehouse/Location;
- AVAILABLE disposition;
- current eligible physical quantity;
- Product/Variant/UOM;
- lot/serial/tracking;
- expiry;
- operation identity.

Pick has STOCK = NONE.

Picked quantity is operational evidence only.

### G. FIFO / FEFO recommendation

Create a rebuildable Warehouse allocation projection from Inventory Ledger; it is not quantity authority.

FEFO:
- expiry-controlled eligible Lot -> earliest non-expired expiry;
- tie-break by FIFO lineage, then stable identity.

FIFO:
- deterministic oldest eligible receipt/availability lineage from Inventory movement sequence/time;
- stable identity tie-break.

Strategy override:
- warehouse.pick.strategy_override;
- mandatory reason;
- default recommendation evidence retained;
- chosen source must remain AVAILABLE, unexpired and otherwise eligible.

An override cannot bypass negative-stock, tracking or disposition rules.

### H. Sales Dispatch source binding

Current Sales Dispatch lines may carry optional Location/Lot/Serial before POST.

WAREHOUSE-IMP-001 may add a narrow Sales-owned application contract that allows Warehouse to bind/update exact pick-selected source positions on an unposted Dispatch line using:
- Dispatch id;
- Dispatch expected version;
- Dispatch line id;
- exact Location/Lot/Serial selection;
- picked quantity consistency;
- Warehouse identity;
- operation key.

Sales persistence remains owner of Sales tables.
Warehouse must never update Sales tables directly.

Dispatch POST continues to perform final authoritative stock validation and STOCK OUT.

### I. Packing / packages

Warehouse owns:
- Package;
- Package Item;
- optional parent handling-unit relation when needed;
- Dispatch relation;
- exact Dispatch line/Product/Lot/Serial quantity;
- package work state;
- optional carrier/tracking/label reference strings.

Cumulative packed <= picked.

Packing has STOCK = NONE.

Exact external carrier/label provider integration is excluded.

Exact dimensional/weight measurement policy is not invented. If captured, values must carry an explicit unit code; no implicit unit convention is authoritative.

### J. Staging / loading

Warehouse stage/load work:
- Dispatch/package checklist;
- current picked/packed quantity;
- package completeness;
- lot/serial consistency;
- optional carrier/load context;
- operation state/version.

Stage/load has STOCK = NONE.

Warehouse exposes dispatch operational readiness.
Sales remains owner of Dispatch READY/POSTED state.

### K. Warehouse transfer

Warehouse owns Transfer header/lines/workflow.

States:
DRAFT
-> ISSUED
-> PARTIALLY_RECEIVED
-> RECEIVED
-> RECONCILIATION_REQUIRED
-> CLOSED

DRAFT may CANCEL.
Posted physical effects reverse by compensating movements.

ISSUE:
- source AVAILABLE position -> TRANSIT;
- uses Inventory physical authority;
- company net quantity 0;
- source negative stock blocked;
- source scope required.

Technical TRANSIT representation for this tranche:
- target Warehouse owns the in-transit Inventory position;
- Location = null;
- disposition = TRANSIT;
- Product/Variant/Lot/Serial preserved.

This gives deterministic inbound visibility and keeps TRANSIT physical quantity inside the target Warehouse scope while conserving company total.

RECEIVE:
- target-Warehouse TRANSIT -> target stock-bearing Location;
- target disposition AVAILABLE by default;
- DAMAGED or QUALITY_HOLD allowed only as explicit receive exception;
- partial receive allowed;
- unresolved remainder remains TRANSIT.

Transfer line tracks:
- requested;
- issued;
- received;
- damaged received;
- resolved loss;
- unresolved transit.

Cross-company transfer is forbidden.

### L. Transfer reconciliation / loss

Damage physically received remains company quantity and enters DAMAGED or QUALITY_HOLD.

Transit shortage/loss remains TRANSIT until explicit reconciliation.

Loss adjustment:
- distinct permission;
- mandatory reason;
- shared approval evidence;
- creator/reconciler cannot approve own adjustment;
- after approval, Inventory physical source-only movement removes exact unresolved TRANSIT quantity;
- Warehouse posts no Finance entry.

Transfer close requires no unresolved transit.

### M. Transfer reversal

Before target receive:
- reverse ISSUE by compensating TRANSIT -> original source AVAILABLE when exact physical/business state still permits.

After receive:
- preserve history;
- use compensating movement(s), never delete/update Inventory movement history.

No generic silent transfer reset.

### N. Stock count

Warehouse owns Count Session, Count Lines and immutable Count Observations.

States:
DRAFT
-> COUNTING
-> REVIEW
-> PENDING_APPROVAL when non-zero discrepancy exists
-> APPROVED
-> POSTED
-> CLOSED

DRAFT may CANCEL.

Count scope is explicit and normalized:
- Warehouse;
- selected Locations;
- selected Product/Variant;
- selected dispositions;
- optional Lot/Serial.

At COUNTING start:
- record start time;
- record deterministic Inventory movement boundary (max committed movement id / equivalent PostgreSQL snapshot marker);
- persist expected-start quantity per count line/scope.

Normal movements continue.

Review:
- calculate net intervening movement after snapshot boundary;
- expected_reconciliation = expected_start + net_intervening;
- discrepancy = accepted_count - expected_reconciliation.

First count is blind by default.
Recount creates a new immutable observation.

Zero discrepancy:
- can close without Inventory movement.

Non-zero:
- review + approval required;
- actor who counted/accepted physical quantity cannot approve own non-zero adjustment.

COUNT POST:
- negative discrepancy may post source-only COUNT_ADJUSTMENT through Inventory.
- positive discrepancy remains fail-closed with POSITIVE_VALUATION_REQUIRED until Finance/Costing valuation authority exists.
- no stock overwrite.

COUNT correction after POST uses reversal/new adjustment.

### O. Damage / scrap

Damage recording:
- authorized disposition movement to DAMAGED or QUALITY_HOLD;
- no quantity creation/destruction.

Scrap workflow:
REQUESTED -> PENDING_APPROVAL -> APPROVED -> POSTED
with eligible cancellation before POST.

Source must be eligible non-available quantity, normally DAMAGED / REWORK / QUALITY_HOLD.

Requires:
- permission;
- reason;
- approval/SoD;
- exact source position;
- Inventory source-only physical movement.

No permanent SCRAP physical disposition is added.

Finance valuation/write-off remains external.

### P. Warehouse/Location deactivation blocker integration

Existing Inventory master deactivation already checks on-hand/Reservation but does not know Warehouse operational work.

WAREHOUSE-IMP-001 adds a narrow Inventory-owned blocker contract, implemented by Warehouse, so final Warehouse/Location INACTIVE also rejects while relevant open:
- put-away;
- pick/pack/stage/load;
- transfer/TRANSIT;
- count;
- scrap/disposition operational work
exists.

Inventory remains owner of Warehouse/Location master state.
Warehouse does not update Inventory master tables directly.

### Q. Offline / scan operation journal

Implement server-side Warehouse operation identity/conflict journal:
- client_operation_id;
- actor;
- Company;
- Warehouse;
- work/document reference;
- operation type;
- local timestamp;
- expected version;
- scan payload identity;
- sync/result/conflict state;
- server result reference;
- correlation.

Unique deterministic retry identity:
Company + client_operation_id.

Same operation retry:
- returns prior logical result / idempotent outcome.

Stale/conflicting state:
- explicit conflict evidence;
- no silent overwrite.

This tranche does not implement a mobile shell, device authentication provider, scanner driver or device-layer adapter. Device identity beyond the authenticated actor/client operation reference is non-authoritative until Device Layer implementation.

### R. Read models / reports

Rebuildable reads:
- receiving/quarantine queue;
- disposition/put-away remaining;
- Reservation operational visibility;
- Dispatch readiness;
- pick progress;
- package/pack/stage/load progress;
- transfer unresolved transit;
- count snapshot/intervening/discrepancy;
- lot/serial trace;
- quarantine/hold aging;
- damage/scrap;
- offline conflicts.

Reports never become Inventory truth.

## 6. Expected persistence

New schema:
warehouse

Expected normalized structures; Database Architect may rename while preserving grain/invariants:
- putaway_works
- putaway_work_lines
- pick_works
- pick_work_lines
- pick_strategy_overrides
- packages
- package_items
- stage_load_works or normalized stage/load records
- warehouse_transfers
- warehouse_transfer_lines
- warehouse_transfer_effect_links
- stock_count_sessions
- stock_count_scopes
- stock_count_lines
- stock_count_observations
- stock_count_inventory_effect_links
- warehouse_scrap_requests
- warehouse_scrap_effect_links
- warehouse_disposition_works/effect_links where work evidence is required
- warehouse_offline_operations

Do not add mutable authoritative stock columns.

Internal PK = BIGINT.
Public id = UUID.
Company scope explicit.
Warehouse/Location foreign keys exact.
Quantities use existing Inventory-compatible decimal precision.
Version/concurrency tokens on mutable operational work.
Posted effect links are immutable/append-oriented.

## 7. Transaction composition

All commands that create Inventory movements execute:
Warehouse state/work mutation
+ Inventory physical movement(s)
+ effect links
+ audit
+ durable idempotency
+ outbox where applicable
inside one PostgreSQL transaction.

Existing EfInventoryPersistence joins ambient MarsDbContext transactions and remains the physical posting engine.

Transfer ISSUE/RECEIVE and multi-line disposition/count/scrap operations use deterministic sub-operation keys so retries cannot duplicate individual Inventory movements.

## 8. Concurrency / locking

Durably protect:
- pick cumulative <= Dispatch line/source remainder;
- pick cumulative <= Reservation remainder when linked;
- pack cumulative <= picked;
- duplicate stage/load completion;
- transfer ISSUE duplicate;
- transfer RECEIVE cumulative <= issued;
- unresolved transit reconciliation;
- count POST duplicate;
- count review against snapshot + intervening movement;
- scrap duplicate;
- disposition quantity cap;
- put-away/replenishment source race;
- serial exact-current-position race;
- offline duplicate retry;
- Warehouse/Location deactivation race.

Inventory authority remains final source-position / negative-stock / serial validation.

## 9. API surface

Expected protected families:

- /api/v1/warehouse/receiving
- /api/v1/warehouse/dispositions
- /api/v1/warehouse/putaway
- /api/v1/warehouse/replenishment
- /api/v1/warehouse/picks
- /api/v1/warehouse/packages
- /api/v1/warehouse/staging
- /api/v1/warehouse/loading
- /api/v1/warehouse/transfers
- /api/v1/warehouse/counts
- /api/v1/warehouse/scrap
- /api/v1/warehouse/trace
- /api/v1/warehouse/offline-operations

Do NOT add:
- Warehouse Dispatch POST route;
- Warehouse Goods Receipt POST route;
- Finance valuation/accounting route;
- Quality inspection-result authority route.

## 10. Mars.Web /warehouse

Create one Warehouse workspace using Mars.UI.

Desktop:
- Receiving/Quarantine
- Put-away/Disposition
- Reservations read
- Picking
- Packing/Packages
- Stage/Load
- Transfers
- Stock Counts
- Damage/Scrap
- Trace
- Offline/Scan conflicts

Mobile responsive behavior is scan/task oriented, not a scaled dense desktop grid.

Required visible semantics:
- Reservation labelled non-physical commitment;
- Pick/Pack/Stage/Load labelled operational, no stock posting;
- Dispatch POST shown as Sales-owned;
- Receipt POST shown as Purchasing-owned;
- QUARANTINE explicit;
- TRANSIT unresolved quantity explicit;
- count first observation blind;
- no Set Stock action;
- hard scan mismatch error;
- positive count adjustment valuation blocker visible.

No React/Vue/Angular/Bootstrap/Tailwind/jQuery.

## 11. Excluded from WAREHOUSE-IMP-001

- Sales Dispatch commercial lifecycle or Dispatch POST ownership;
- Purchasing Goods Receipt creation/POST ownership;
- Supplier/Sales Invoice financial posting;
- Finance Inventory Valuation / COGS / write-off posting;
- positive Count Adjustment physical POST until Finance valuation authority exists;
- full Quality inspection-plan/result module;
- automatic replenishment thresholds/optimizer;
- MRP;
- carrier API, label provider, tracking provider;
- robotics/WMS provider;
- mobile/Desktop shell implementation;
- scanner/device driver and Device Layer;
- exact location capacity/cube/weight policy administration;
- production deployment;
- Full Test Day.

## 12. Normal acceptance evidence

When implementation completes, require direct evidence for:

Domain/application:
- permission catalog includes PLAN-006 permissions and no negative-stock override;
- Warehouse access scope on state-changing commands;
- receiving queue is read-only over Purchasing source;
- disposition net quantity 0;
- put-away net quantity 0;
- replenishment net quantity 0;
- pick/pack/stage/load STOCK = NONE;
- AVAILABLE-only pick;
- expired/blocked pick rejected;
- FEFO/FIFO deterministic recommendation;
- strategy override reason/permission and eligibility retained;
- Sales Dispatch source binding is pre-POST and Sales-owned;
- pack <= picked;
- transfer issue/receive conserves company quantity;
- partial receive leaves TRANSIT;
- loss adjustment approval/SoD;
- count snapshot + intervening reconciliation;
- non-zero count approval/SoD;
- negative count adjustment delegates Inventory;
- positive count adjustment fails closed without valuation authority;
- scrap approval/SoD and physical OUT;
- Warehouse/Location deactivation sees open Warehouse work;
- offline retry idempotency/conflict evidence.

Persistence:
- normalized Warehouse schema;
- no stock balance authority column;
- Inventory movement effect links retained;
- migration additive/safe;
- pending model clean.

Web/API:
- /warehouse 200 on TEST;
- expected protected Warehouse API surface 401 unauthenticated;
- no Warehouse Dispatch POST;
- no Warehouse Goods Receipt POST;
- no Finance/Quality posting surface;
- OpenAPI expected.

Regression:
- /purchasing /sales /inventory /products /parties = 200;
- health/live and health/ready = 200.

Normal build:
- frontend targeted tests;
- Warehouse targeted Foundation tests;
- Release build 0 errors;
- migration safety;
- EF pending-model;
- API/OpenAPI smoke;
- TEST deploy/smoke.

Do not claim authenticated Warehouse mutation unless direct evidence exists.

## 13. Full Test Day deferred

Do not run during WAREHOUSE-IMP-001 normal implementation:
- broad authenticated permission matrix / IDOR;
- high-contention multi-picker races;
- high-contention transfer issue/receive races;
- count under sustained concurrent movement;
- offline multi-device reordering;
- serial high-contention;
- browser/mobile full scan E2E;
- performance/load;
- security regression;
- backup/restore;
- full finance valuation invariants after Finance exists.

## 14. Decision

The exact broad coherent Warehouse scope is frozen.

Assigned implementation work package:
WAREHOUSE-IMP-001 — Warehouse Execution & Inventory Control Authority Tranche

Status:
READY FOR IMPLEMENTATION

No smaller Warehouse micro-package IDs are created.
