# Warehouse UI / Form Contract

Status: FROZEN — PLAN-006

## 1. Personas

- receiving operator;
- picker/packer;
- warehouse supervisor;
- inventory controller/counter;
- mobile scanner user.

Desktop is dense operational control.
Mobile is scan-first single-task execution.

## 2. Warehouse list/detail

V38-aligned list:
- Warehouse Code
- Warehouse
- Branch
- Type
- Location count
- Available
- Quarantine
- Transit
- State

Detail:
- General
- Locations
- Stock projection
- User scopes
- Counts
- Timeline

Negative stock is displayed as project policy: BLOCK.

No editable current-stock field.

## 3. Location

List/detail:
- Warehouse
- Location
- zone/type
- state policy
- capacity/occupancy projection
- ACTIVE/INACTIVE

Show clearly:
- stock-bearing vs aggregate;
- allowed/blocked dispositions if configured;
- current derived quantity;
- open work.

Deactivate action reports exact blockers rather than silently moving stock.

## 4. Receiving / put-away

Mobile receiving:
1. scan Receipt/PO;
2. scan Product;
3. quantity;
4. lot/serial/expiry;
5. receiving Location;
6. POST confirmation.

After receipt:
QUARANTINE badge is explicit.

Put-away:
- source;
- target recommendation;
- capacity/state-policy warning;
- scanned Product/lot/serial;
- quantity;
- remaining;
- Complete.

## 5. Quarantine / disposition

V38-style queue:
- source;
- document;
- Product;
- quantity;
- QC plan/result;
- disposition remaining;
- wait age;
- state.

Disposition form:
- subject quantity;
- previously disposed;
- remaining;
- action;
- target Location;
- quantity;
- reason.

QC result and stock disposition are separate labels.

## 6. Reservation

Read/operational screen:
- Reservation;
- Sales Order;
- Customer;
- Product;
- Warehouse;
- requested;
- reserved;
- consumed;
- remaining;
- state.

Reservation is labeled "commitment — not physical stock".

## 7. Picking

Pick screen/mobile:
- Dispatch/Order;
- line;
- requested/remaining;
- Reservation context;
- recommended Location/lot/serial;
- strategy FIFO/FEFO;
- available quantity;
- picked this action;
- picked total;
- remaining.

Scan Product → Location → Lot/Serial → Qty.

Wrong scan = hard error with expected vs scanned identity.

Override allocation:
- separate supervisor action;
- reason required.

## 8. Packing / staging / loading

Packing:
- Packages;
- package items;
- box/pallet relation;
- weight/dimensions;
- label;
- carrier/tracking.

Show:
picked / packed / remaining.

Staging/loading:
- package checklist;
- pre-shipment QC;
- loaded state;
- carrier handoff readiness.

Do not label Pick/Pack/Load as "stock posted".

Dispatch POST remains explicit high-risk action.

## 9. Transfer

V38-aligned list:
- Transfer No
- Source Warehouse
- Target Warehouse
- Date
- Issued
- Received
- Difference
- State

Detail tabs:
- Lines
- Issue
- Receive
- Differences
- Cost/Value read context
- Timeline

Actions:
- Issue
- Partial Receive
- Receive
- Reconcile
- Close
- Reverse

Show unresolved TRANSIT quantity prominently.

## 10. Stock count

V38-aligned:
- Count No
- Warehouse
- Snapshot
- Start
- Count
- Review
- discrepancy lines
- value difference read context
- State

First counter:
- blind quantity by default.

Review:
- snapshot qty;
- intervening movements;
- expected at reconciliation;
- first count;
- recount;
- difference;
- approval.

Post button only after required approval.

No "Set Stock" action.

## 11. Lot / Serial

List:
- Product;
- lot/serial;
- Warehouse/Location;
- receipt/source;
- received date;
- expiry;
- derived quantity/current state.

Serial:
show one current derived position and full movement history.

Expired stock has explicit normal-pick blocked indication.

## 12. Scan Console

V38 Scan Console remains generic operational entry surface, not business authority.

Each operation clearly shows:
- intended command;
- company/Warehouse;
- work/document source;
- device/user;
- scan identity;
- online/offline;
- pending/synced/conflict.

Intentional second scan is visually distinct from retry.

## 13. Error recovery

Preserve entered/scanned work where safe.

Differentiate:
- validation;
- scan mismatch;
- stale state;
- negative-stock conflict;
- capacity;
- permission;
- offline conflict;
- duplicate/idempotent retry.

Status is never conveyed by colour only.
