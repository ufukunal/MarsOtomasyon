# Warehouse Operational Workflows

Status: FROZEN — PLAN-006

## 1. Inbound receipt handoff

Goods Receipt POST is Purchasing-owned and already creates:
- physical STOCK IN;
- QUARANTINE disposition;
- Product/UOM/lot/serial/Warehouse/Location lineage.

Warehouse begins after/around receipt execution:
1. receiving-dock validation;
2. QC/disposition;
3. release;
4. put-away.

No second STOCK IN is created by put-away.

## 2. Receiving dock

Before Goods Receipt POST validate:
- PO/Receipt source;
- supplier;
- Product/Variant/barcode;
- entered/Base UOM;
- quantity;
- Warehouse/receiving Location;
- lot/serial/expiry;
- package/pallet where captured;
- duplicate scan/work action.

After POST:
- quantity is on-hand in QUARANTINE;
- payable remains unchanged;
- put-away/release work can be created.

## 3. QC / disposition / release

QUARANTINE quantity cannot be normal picked/reserved as AVAILABLE.

Disposition action:
- identifies source receipt/lot/serial/location;
- quantity <= unresolved disposition quantity;
- posts status/location movement;
- outcomes: AVAILABLE, QUALITY_HOLD, REWORK, DAMAGED or return path.

Release to AVAILABLE may include target put-away location selection, but ledger/audit distinguishes disposition release from location move.

## 4. Put-away

Task:
RELEASED/ELIGIBLE → ASSIGNED → IN_PROGRESS → COMPLETED / CANCELLED.

Scan:
- source Product/lot/serial;
- source location;
- target location;
- quantity.

Validation:
- target active/stock-bearing;
- no cross-company/Warehouse mistake;
- state-policy compatible;
- capacity not exceeded where configured;
- serial uniqueness/current position.

Completion posts internal location movement, net company quantity zero.

Partial put-away allowed; remainder stays at source location.

## 5. Reservation visibility

Warehouse reads active Reservation:
- source Sales Order/line;
- Product/Variant;
- Warehouse scope;
- requested/reserved/consumed/remaining.

Warehouse does not convert Reservation into physical stock status.

Release/reallocation is an explicit Reservation action.

## 6. Dispatch work / picking

Sales Dispatch:
DRAFT → PICKING → READY → POSTED → HANDED_OVER → DELIVERED.

Warehouse picking work:
OPEN → IN_PROGRESS → PICKED / PARTIALLY_PICKED → CLOSED/CANCELLED.

Pick validation:
- dispatch/source line remainder;
- Reservation remainder if linked;
- AVAILABLE stock;
- Warehouse/Location;
- FIFO/FEFO recommendation;
- barcode;
- lot/serial;
- quantity.

Picking records work only; STOCK effect = NONE.

Partial pick allowed.
Over-pick blocked.

## 7. FIFO / FEFO allocation

Expiry controlled:
- candidate sort = earliest valid expiry, then oldest receipt/availability lineage, then stable identity.

No expiry:
- candidate sort = oldest receipt/availability lineage, then stable identity.

Override:
- explicit action;
- permission;
- reason;
- cannot select expired/blocked quantity.

## 8. Packing

Packing:
- consumes picked operational quantity into package structure;
- no Inventory Ledger posting.

Package:
- belongs Dispatch;
- can contain multiple items;
- line can span packages;
- optional box/pallet hierarchy can be recorded;
- weight/dimensions/label/carrier/tracking captured when applicable.

Cumulative packed <= picked.

## 9. Staging / loading

Operational verification:
- package present;
- source Dispatch;
- carrier/loading context;
- lot/serial/package completeness;
- pre-shipment QC if required.

No STOCK effect.

If loaded shipment is cancelled before POST:
- return work state to eligible warehouse process;
- no reversal needed because no stock was posted.

## 10. Dispatch POST

Final authoritative validation rechecks current stock/lot/serial/source quantities.

Atomic effect:
- Sales Dispatch POST;
- Inventory Ledger STOCK OUT;
- Reservation consumption/release;
- audit/outbox.

After POST:
- handover/delivery are logistics states only.
- Invoice remains STOCK=NONE.

Reverse:
- compensating physical effect with original link;
- downstream/return conflicts validated.

## 11. Warehouse transfer

### Draft

Select:
- source Warehouse/Location;
- target Warehouse/Location;
- Product/Variant;
- lot/serial;
- qty.

### Issue

Atomic:
- source AVAILABLE OUT;
- TRANSIT IN;
- transfer line issued quantity increases.

Negative source stock blocked.

### Partial receive

For part of TRANSIT:
- TRANSIT OUT;
- target IN;
- status AVAILABLE unless damage/hold exception.

Remainder stays TRANSIT.

### Full receive

All eligible transit resolved into target and no variance.

### Reconciliation

If short/lost/damaged:
- physical received damage goes DAMAGED/QUALITY_HOLD;
- short remains TRANSIT;
- explicit loss adjustment required to remove unresolved transit;
- reason/approval/audit;
- then transfer can close.

## 12. Transfer reversal

Before target receipt:
- ISSUE reversal can move TRANSIT back to original source if physical/business state supports it.

After target receipt:
- do not delete transfer history;
- use reverse/return transfer effects sufficient to restore authoritative positions.

## 13. Stock count

### Create

Choose explicit scope:
- Warehouse;
- Locations;
- Products/categories where allowed;
- dispositions;
- lot/serial.

### Start

- capture ledger snapshot/version/time;
- first count values are blind by default;
- normal operations may continue.

### Count

Scan/enter actual physical quantity.
Serial counts enumerate serials.

### Intervening movements

System tracks movements after snapshot for counted scope.

### Review

Calculate expected-at-reconciliation:
snapshot + net intervening movements.

Difference:
counted - expected.

Zero:
close.

Non-zero:
review/recount → approval.

### Post

Approved difference creates COUNT_ADJUSTMENT ledger movement.

No direct stock overwrite.

## 14. Count recount

Reviewer can request recount for:
- suspicious discrepancy;
- serial mismatch;
- policy threshold;
- operator confidence issue.

Recount is a new counted observation linked to same session, not overwrite without history.

## 15. Damage / scrap

Damage incident:
- explicit status move to DAMAGED/QUALITY_HOLD where physically present.

Scrap:
- source non-available quantity;
- permission/reason;
- approval;
- explicit DISPOSAL/STOCK OUT;
- valuation impact delegated to Finance.

## 16. Warehouse/Location deactivation

Attempt:
- calculate current ledger on-hand;
- Reservations;
- Transit;
- open tasks/counts/dispatches/receipts.

Any unresolved item → BLOCK.

No automatic stock transfer.

## 17. Mobile/offline

Online scan:
server validates every mutation.

Offline:
- queue client_operation_id;
- preserve ordered scan/work intent;
- sync later.

Sync:
- duplicate same operation → idempotent prior result;
- stale source/current-position conflict → visible conflict;
- no silent overwrite.

Intentional repeated scan uses a new operation identity.

## 18. Error model

Hard conflicts:
- negative result;
- wrong Product/barcode;
- wrong lot/serial;
- expired normal pick;
- inactive Warehouse/Location;
- capacity breach;
- stale Dispatch/Reservation/Transfer/Count;
- duplicate post;
- cross-company;
- serial double-position;
- unresolved transfer loss;
- unapproved count/scrap adjustment.
