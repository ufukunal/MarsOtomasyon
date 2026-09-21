# Warehouse Operations Domain Plan

Status: COMPLETED / FROZEN — PLAN-006

## 1. Objective

Freeze physical Warehouse execution so inventory movements, operational work states, Reservation commitments and commercial documents cannot double-post or silently lose quantity.

## 2. Sources

Repository authority:
- governance/planning standard;
- Inventory Ledger and snapshot design principles;
- frozen Sales PLAN-002;
- frozen Product/Inventory PLAN-004;
- frozen Purchasing PLAN-005;
- Warehouse/ERP skill contracts;
- V38 repository HTML Warehouse/Dispatch/Quality product reference.

Owner instruction authorizes practical inference from repository + skills + V38 without routine internet research.

## 3. Frozen decisions

### WH-D001 — Negative stock

Normal Warehouse operations may not make authoritative physical quantity negative at the required Product/Variant/Warehouse/Location/status/lot/serial scope.

BLOCK applies to:
- Dispatch POST;
- Transfer ISSUE;
- Scrap/disposal;
- physical Purchase Return;
- manual operational adjustment other than controlled count reconciliation.

Reservation/pick also cannot intentionally commit/process more than eligible quantity.

There is no ordinary user override to post negative stock in PLAN-006.

If legacy/import/recovery ever needs negative inventory, it requires a future explicit owner/Finance/DB recovery policy; it is not a normal Warehouse permission.

### WH-D002 — Pick eligibility

Normal pick candidate must be:
- same company;
- STOCKABLE Product/Variant;
- active Warehouse/Location;
- physical disposition AVAILABLE;
- positive eligible on-hand;
- matching lot/serial/tracking requirements;
- not expired when expiry-controlled;
- within source Sales Dispatch/Order remainder;
- within Reservation scope when an active Reservation is explicitly linked.

QUARANTINE, QUALITY_HOLD, REWORK, DAMAGED and TRANSIT are not normal pick candidates.

### WH-D003 — FIFO / FEFO

Default:
- expiry-tracked eligible stock → FEFO by earliest valid expiry;
- non-expiry tracked eligible stock → FIFO by oldest authoritative receipt/availability lineage.

This is recommendation/allocation order, not a separate quantity truth.

Override:
- permission `warehouse.pick.strategy_override`;
- mandatory reason;
- actor/time/audit;
- selected stock must still be AVAILABLE, unexpired and otherwise eligible.

An override cannot pick expired or blocked stock.

### WH-D004 — Reservation and picking

Reservation remains non-physical.

Pick may be created:
- from a Dispatch/Order line with no Reservation, provided eligible physical availability and source remaining exist; or
- against an active Reservation.

If a Reservation is linked:
- picked quantity cannot exceed that Reservation's remaining eligible quantity without first performing an explicit Reservation release/reallocation/new Reservation operation;
- pick does not itself consume authoritative Reservation;
- Dispatch POST consumes/releases related Reservation according to frozen Sales contract.

Always:
`picked_qty <= dispatch/source remaining`
and
`picked_qty <= currently eligible physical quantity at validation`.

### WH-D005 — Picking has no inventory posting

Picking:
- records work execution/source location/lot/serial/picked quantity;
- may mark operational PICKED/STAGED work state;
- does not create company STOCK OUT;
- does not silently relocate authoritative quantity in Inventory Ledger.

Packing/staging/loading likewise do not create Sales stock movement.

The authoritative outbound effect occurs only at Sales Dispatch POST.

This preserves PLAN-002 and avoids pick + Dispatch double-post.

### WH-D006 — Package / packing

Dispatch may contain many Packages.
A Package may contain many package items.
A Dispatch line may be split across many Packages.

Operational package data may include:
- package/box/pallet identity;
- contained Product/Variant/lot/serial quantities;
- weight/dimensions;
- label/tracking/carrier references.

Packing validates cumulative packed quantity <= picked/eligible Dispatch quantity.

Packing does not post stock.

### WH-D007 — Staging/loading/final verification

Before Dispatch POST:
- source Dispatch is current and post-eligible;
- picked/packed quantities are consistent;
- required lot/serial exact;
- packages/loading verification complete where used;
- no stale Reservation/source conflict;
- current authoritative stock still supports POST.

POST transaction:
- Dispatch becomes POSTED;
- Inventory Ledger STOCK OUT is created atomically;
- related Reservation is consumed/released atomically as required;
- lot/serial/source location lineage retained.

Handoff/delivery after POST does not post stock again.

### WH-D008 — Put-away

Goods Receipt has already posted STOCK IN to QUARANTINE.

Put-away:
- is an internal physical location movement;
- does not create new company stock;
- moves quantity from current location to target Location through authoritative inventory movement history;
- preserves current disposition unless a separate disposition action is posted.

Normal released stock flow:
Goods Receipt QUARANTINE
→ QC/disposition RELEASE to AVAILABLE
→ put-away AVAILABLE quantity to eligible stock-bearing target Location.

A combined UI action may orchestrate release + put-away, but the two effects remain conceptually distinct and auditable.

Target validation:
- same company/Warehouse context;
- active stock-bearing Location;
- Product/location state-policy compatibility;
- configured capacity not exceeded where capacity is enforced.

Capacity failure = BLOCK; user selects another eligible Location.

### WH-D009 — Warehouse transfer

Transfer states:
DRAFT
→ ISSUED
→ PARTIALLY_RECEIVED
→ RECEIVED
→ RECONCILIATION_REQUIRED when variance exists
→ CLOSED.

DRAFT may be CANCELLED.
Posted transfer effects reverse by compensating movements.

ISSUE:
- validates eligible source AVAILABLE quantity;
- source location/status STOCK OUT;
- same company TRANSIT STOCK IN;
- company total on-hand unchanged.

RECEIVE:
- TRANSIT STOCK OUT;
- target Warehouse/Location STOCK IN;
- default target disposition AVAILABLE unless transfer exception requires DAMAGED/QUALITY_HOLD;
- company total on-hand unchanged.

Cross-company transfer is forbidden; intercompany movement would be a different future commercial workflow.

### WH-D010 — Partial transfer receipt

Partial RECEIVE is allowed.

For each line:
- issued_qty;
- received_qty;
- damaged_received_qty;
- unresolved_transit_qty.

Until reconciliation:
`unresolved_transit_qty = issued - received - explicitly_resolved_loss`

Unreceived quantity remains TRANSIT.

A partial receive cannot silently close or remove remainder.

### WH-D011 — Transfer shortage / loss / damage

Damage physically received:
- receive into DAMAGED or QUALITY_HOLD target disposition;
- quantity remains company on-hand.

Short/lost in transit:
- remains TRANSIT until reconciliation;
- closing shortage requires explicit loss/adjustment posting with reason, permission and approval;
- adjustment removes quantity from company on-hand and retains transfer lineage.

No "receive less and forget remainder" behavior.

### WH-D012 — Stock count lifecycle

Count states:
DRAFT
→ COUNTING
→ REVIEW
→ PENDING_APPROVAL when discrepancy exists
→ APPROVED
→ POSTED
→ CLOSED.

DRAFT may CANCEL.
Posted adjustment correction uses reversal/new count adjustment, not edit.

Start Count:
- captures authoritative Inventory Ledger expected snapshot/version/time for selected count scope;
- freezes count scope, not the whole Warehouse;
- first operator count is blind by default.

Intervening normal movements are allowed and tracked.

At review:
`expected_at_reconciliation = start_snapshot + net_intervening_movements`.

Compare physical counted quantity to expected-at-reconciliation, not to a stale mutable stock field.

### WH-D013 — Recount / approval

Zero discrepancy:
- can close without Inventory Ledger adjustment.

Non-zero discrepancy:
- requires REVIEW;
- recount may be requested/required by reviewer;
- requires approval before POST;
- counter cannot approve own non-zero adjustment.

Exact monetary/quantity threshold for mandatory second recount is configurable Warehouse Count Policy; absent policy, reviewer decides whether to request recount, but non-zero adjustment still requires approval.

### WH-D014 — Count adjustment

POST of approved discrepancy creates explicit `COUNT_ADJUSTMENT` Inventory Ledger movement equal to:

`counted_qty - expected_at_reconciliation`.

Never:
`stock = counted_qty`.

Adjustment preserves:
- count session/scope;
- Product/Variant/UOM;
- Warehouse/Location/status;
- lot/serial;
- actor/approver;
- reason;
- snapshot and intervening movement evidence.

Positive adjustments require an accepted valuation basis from Finance/Costing at implementation time; zero-cost positive stock is not silently invented. Exact valuation method remains PLAN-007/P3 responsibility and does not change Warehouse quantity semantics.

### WH-D015 — Lot / serial scan mismatch

Hard BLOCK:
- barcode resolves wrong Product/Variant;
- wrong Warehouse/Location;
- lot belongs to another Product/Variant;
- serial belongs to another Product/Variant;
- serial already at incompatible position/state;
- expired lot for normal pick;
- serial quantity fractional;
- required lot/serial absent.

No generic "accept anyway" scan permission.

Corrections require fixing source/master/work context or an explicit owning correction workflow.

### WH-D016 — Warehouse/Location deactivate

Warehouse final INACTIVE requires:
- on-hand = 0 across physical dispositions;
- active Reservations = 0;
- TRANSIT directed to/from Warehouse resolved;
- open receiving/pick/dispatch/transfer/count work resolved.

Location final INACTIVE requires:
- on-hand = 0;
- no active operational work/reservation relying on it.

History remains readable.
No deactivation deletes or relocates stock automatically.

### WH-D017 — Damage / rework / quarantine

Disposition changes are physical Inventory Ledger status effects and preserve quantity.

Examples:
- QUARANTINE → AVAILABLE;
- QUARANTINE → QUALITY_HOLD;
- QUALITY_HOLD → REWORK;
- AVAILABLE → DAMAGED when an accepted warehouse incident posts the status change.

Status change never creates/deletes quantity by itself.

### WH-D018 — Scrap / disposal

SCRAP is not a permanent on-hand disposition.

Physical scrap/disposal:
- source quantity must be in eligible non-available disposition, normally DAMAGED/REWORK/QUALITY_HOLD;
- explicit permission + reason;
- approval required;
- posts Inventory Ledger STOCK OUT / DISPOSAL;
- original source/quality/incident lineage retained;
- ACCOUNT/CASH effects are none in Warehouse command;
- valuation/accounting consequence belongs Finance/Costing.

### WH-D019 — Offline/mobile scan

Each queued offline mutation carries:
- client_operation_id;
- device/user;
- company/Warehouse;
- local timestamp;
- action/work reference;
- scanned identity;
- expected source version where applicable.

Server sync:
- same client_operation_id retry is idempotent and returns prior logical result;
- server revalidates permission, company, location, source version, quantity, lot/serial and current state;
- stale/conflicting operation is rejected into visible conflict resolution;
- no silent overwrite/reorder that changes business truth;
- HTTP retry identity and intentional second scan/action identity are distinct.

### WH-D020 — Replenishment

Internal replenishment is a Warehouse location-to-location move used to support picking.

It:
- cannot create/destroy company stock;
- requires source AVAILABLE quantity;
- posts internal location movement;
- preserves Product/lot/serial/disposition;
- is not Sales Dispatch or Purchase Receipt.

Automatic replenishment thresholds/optimization are deferred to later MRP/Warehouse optimization planning.

## 4. Effect matrix

| Action | RES | STOCK | ACCOUNT | CASH/BANK | Note |
|---|---:|---|---|---|---|
| Goods Receipt POST | none | IN → QUARANTINE | none | none | Purchasing-owned source |
| QC/Disposition release | none | status/location effect, net qty 0 | none | none | separate from receipt |
| Put-away | none | internal location move, net qty 0 | none | none | no new stock |
| Reservation | +/- commitment | NONE | none | none | non-physical |
| Pick / Pack / Stage / Load | NONE | NONE | none | none | operational work state |
| Dispatch POST | consume/release | OUT | none | none | Sales-owned posting point |
| Transfer ISSUE | none | source OUT + TRANSIT IN | none | none | company net 0 |
| Transfer RECEIVE | none | TRANSIT OUT + target IN | none | none | company net 0 |
| Count POST | none | COUNT_ADJUSTMENT delta | none | none | approved difference |
| Disposition change | none | status move, net qty 0 | none | none | authoritative ledger history |
| Scrap/disposal | none | OUT | none | none | financial value later |
| Replenishment | none | internal location move | none | none | company net 0 |

## 5. Reviewer outcome

Warehouse: all physical recognition and scan/exception paths are traceable.
ERP: no Goods Receipt/Dispatch/Invoice double-post ambiguity.
Database: quantity authority remains Inventory Ledger; work/projection data is secondary.
Architecture: Sales/Purchasing own commercial documents; Warehouse/Inventory own physical execution.
Developer: each mutation has deterministic validation/transaction boundary.
Testing: negative stock, duplicate/offline, count, transfer, lot/serial and Dispatch races are testable.
UX: scan-first workflows expose source/current/remaining/conflict state.
Accounting: Warehouse does not invent valuation/account postings; count/scrap valuation delegates to Finance.
