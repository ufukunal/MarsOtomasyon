# Warehouse Conceptual Data Contract

Status: FROZEN logical/domain planning only. No physical SQL schema, index or column type is defined here.

## 1. Authority model

Inventory authoritative:
- posted physical Inventory Ledger movements;
- current quantity reconstructed/projected from ledger;
- physical Warehouse/Location/status/lot/serial lineage;
- Reservation commitment authority.

Warehouse operational authority:
- receiving/put-away/pick/pack/stage/load work records;
- transfer document/work state;
- count session/observations/review;
- operational incident/disposition/scrap requests where Warehouse owns them;
- scan/offline operation identity and conflict state.

Sales authoritative:
- Sales Dispatch commercial document/state and source Order relationship.
- Dispatch POST is the Sales outbound posting command.

Purchasing authoritative:
- Goods Receipt document and Purchase Order relationship.
- Goods Receipt POST is inbound STOCK IN.

Quality authoritative:
- inspection/QC result when Quality workflow applies.

Finance/Costing authoritative:
- inventory valuation/cost layers;
- financial value of count/scrap/transfer differences;
- accounting effects.

## 2. Conceptual entities

### Warehouse Work

Generic planning concept for operational work references, not permission to create one mega-table.

Specialized work types may include:
- PutAwayWork
- PickWork
- PackWork
- LoadWork
- ReplenishmentWork

Each conceptually carries:
- company/Warehouse;
- source document/work identity;
- state;
- actor/assignee;
- Product/Variant;
- requested/processed/remain quantities;
- expected version;
- audit/timestamps.

Exact physical modeling is P3 responsibility.

### Pick Work / Pick Line

References:
- Sales Dispatch and exact source line/version;
- optional Reservation;
- Product/Variant/UOM;
- recommended and actual source Location;
- lot/serial;
- requested/picked quantity;
- strategy/recommendation evidence;
- override reason when used.

Pick quantity is operational, not authoritative STOCK OUT.

### Package / Package Item

Package belongs to Dispatch/shipment context.

Conceptually:
- package identity/type;
- optional parent pallet/container;
- weight/dimensions;
- label/carrier/tracking;
- state.

Package Item links:
- package;
- Dispatch line;
- Product/Variant;
- quantity;
- lot/serial where required.

Relations:
- one Dispatch → many Packages;
- one Package → many Package Items;
- one Dispatch line → many Package Items.

No comma-separated line/product IDs.

### Warehouse Transfer

Company-scoped internal physical move.

Conceptually:
- source Warehouse;
- target Warehouse;
- state;
- lines;
- issue/receive/reconciliation evidence;
- audit.

Cross-company Warehouse Transfer is forbidden.

### Transfer Line

References:
- Product/Variant/UOM;
- source Location/status/lot/serial;
- target Location;
- requested_qty;
- issued_qty;
- received_qty;
- damaged_received_qty;
- resolved_loss_qty;
- unresolved_transit_qty.

Derived:
`unresolved_transit_qty = issued_qty - received_qty - resolved_loss_qty`

Damage received remains quantity and is included in received physical quantity, with DAMAGED/QUALITY_HOLD disposition.

### Stock Count Session

Defines immutable count scope and workflow.

Conceptually:
- company/Warehouse;
- selected Locations/products/dispositions;
- snapshot timestamp/version/reference;
- state;
- counter/reviewer/approver;
- policy version;
- audit.

### Count Line / Observation

Conceptually:
- count session;
- Product/Variant;
- Location/status;
- lot/serial;
- expected_start_qty;
- net_intervening_qty;
- expected_reconciliation_qty;
- first_count_qty;
- optional recount observations;
- accepted_count_qty;
- discrepancy_qty;
- reason/review/approval evidence.

Observations are retained; recount does not erase first count.

### Offline Scan Operation

Conceptually:
- client_operation_id;
- device/user;
- company/Warehouse;
- operation type;
- work/document reference;
- local timestamp;
- expected source version;
- scan payload identity;
- sync/result/conflict state;
- server result reference.

It is an idempotency/operational command record, not inventory truth.

## 3. Physical ledger dimensions

A posted inventory movement carries applicable:
- company;
- Product/Variant;
- entered UOM;
- base-normalized quantity;
- source/target Warehouse;
- source/target Location;
- source/target disposition;
- Lot;
- Serial;
- source document/work line;
- posting time;
- actor;
- original/reversal link;
- correlation/idempotency reference.

P3 decides exact normalized relational representation.

## 4. Movement semantics

### Goods Receipt

Purchasing source:
external/supplier → Warehouse receiving location / QUARANTINE.

### Disposition

Same physical quantity:
source status/location → target status/location.
Net company quantity = 0.

### Put-away / Replenishment

Internal location move:
source Location OUT + target Location IN.
Net Warehouse/company quantity = 0.

### Sales Dispatch

Warehouse/company stock → external/customer.
Net company quantity decreases.
Owned by Sales Dispatch POST.

### Transfer Issue

source Location AVAILABLE OUT
+ TRANSIT IN.
Net company quantity = 0.

### Transfer Receive

TRANSIT OUT
+ target Location/status IN.
Net company quantity = 0.

### Count Adjustment

Explicit delta at counted physical dimension.
Company quantity changes by discrepancy.

### Scrap / Disposal

eligible on-hand non-available quantity OUT.
Company quantity decreases.

No operation uses a mutable balance overwrite.

## 5. Pick / stock separation

Pick records may derive:
- picked_qty;
- packed_qty;
- staged_qty;
- loaded_qty.

These are Warehouse work-state quantities.

They do not replace:
- Inventory Ledger on_hand;
- Reservation;
- Dispatch posted shipped quantity.

Final Dispatch POST validates current authority again.

## 6. FIFO / FEFO recommendation lineage

Allocation recommendation must be reproducible from:
- tracking/expiry policy;
- expiry date when relevant;
- authoritative receipt/availability lineage timestamp;
- stable tie-break identity.

Override stores:
- default candidate/order;
- selected candidate;
- actor;
- reason;
- time.

Recommendation itself is not authoritative stock allocation until the work command is accepted.

## 7. Count snapshot model

At COUNTING start:
- freeze count scope;
- capture authoritative expected quantity snapshot/version.

Normal inventory movement may continue.

All posted movements affecting scope after snapshot and before reconciliation contribute to `net_intervening_qty`.

`expected_reconciliation_qty = expected_start_qty + net_intervening_qty`

`discrepancy_qty = accepted_count_qty - expected_reconciliation_qty`

COUNT_ADJUSTMENT = discrepancy_qty after approval.

No direct stock replacement.

## 8. Reservation boundary

Reservation:
- source document commitment;
- not physical disposition;
- no Location move;
- no Inventory Ledger quantity effect.

Pick may reference Reservation but does not consume it authoritatively.
Dispatch POST performs accepted Reservation consumption/release.

## 9. Transfer reconciliation

Unresolved transit remains authoritative company on-hand in TRANSIT.

Closing a transfer requires:
- all issued quantity received; or
- remaining quantity resolved by explicit approved loss/adjustment/reversal.

Damaged receipt:
- is received quantity;
- target disposition DAMAGED/QUALITY_HOLD;
- not transit loss.

## 10. Serial invariants

- one physical serial instance has at most one current authoritative position/status;
- serial-tracked pick/transfer/count identifies exact serial;
- fractional serial quantity is invalid;
- duplicate offline/online posting cannot create a second instance;
- location/status changes derive from movement history.

## 11. Warehouse / Location deactivation data rule

Final inactive state must be protected against:
- non-zero on-hand;
- active Reservation using Warehouse scope;
- unresolved TRANSIT;
- open put-away/pick/dispatch/transfer/count work.

No cascade delete of historical ledger/work records.

## 12. Concurrency risks for P3

Durable implementation must protect:
- concurrent Dispatch POST against same stock;
- concurrent picks/reservations;
- duplicate transfer ISSUE/RECEIVE;
- partial receive race;
- serial double-position;
- duplicate count POST;
- count reconciliation vs intervening movement;
- duplicate scrap;
- offline retry duplication;
- stale offline work;
- Warehouse/Location deactivation race.

Exact constraints/locking/version/idempotency implementation belongs P3.

## 13. Forbidden data models

- Warehouse.current_stock authority;
- Location.current_stock authority;
- Pick quantity used as authoritative stock balance;
- physical RESERVED status duplicating Reservation;
- TRANSIT represented only by transfer status without inventory quantity lineage;
- stock count overwrite of current quantity;
- comma-separated package/lot/serial/source IDs;
- silent serial relocation;
- cache as offline sync authority;
- hard delete of posted movement history.
