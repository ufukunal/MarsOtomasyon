# Product / Inventory Master Workflows

Status: FROZEN — PLAN-004

## 1. Create Product

Flow:
1. company context fixed;
2. choose kind GOODS or SERVICE;
3. assign Product Code and names;
4. choose capabilities SELLABLE/PURCHASABLE/STOCKABLE;
5. choose Base UOM;
6. configure tracking strategy for stockable identity;
7. optionally add Variants, alternate UOMs, barcodes and categories;
8. validate duplicate code/barcode rules;
9. save ACTIVE Product with audit.

Effects:
- MASTER only.
- RES/STOCK/ACCOUNT/CASH-BANK/COST = NONE.

SERVICE:
- STOCKABLE forbidden.
- no Reservation/physical inventory/lot/serial.

## 2. Create / maintain Variant

Variant is optional.

Create:
- reference parent Product;
- define operational differentiator/name/code;
- inherit company and Product capability constraints;
- configure applicable UOM/barcode/tracking identity.

Rules:
- if Product has meaningful active Variants, consuming workflows select the concrete Variant when that differentiator affects trade/stock.
- deactivating a Variant blocks new normal selection but not historical lookup.
- variant edit cannot rewrite posted snapshots.

## 3. Base UOM / alternate UOM

Base UOM:
- exactly one current Product quantity normalization basis.

Alternate UOM:
- explicit positive conversion:
  `1 alternate = factor × base`.

Workflow:
1. select UOM;
2. enter factor using decimal;
3. define allowed operational use where later source-backed;
4. validate no duplicate/conflicting active Product-UOM mapping;
5. save/audit.

Changing factor:
- affects future transactions only;
- existing posted document/ledger conversion snapshot remains unchanged;
- if open operational documents rely on old conversion, consuming module must detect stale/version conflict rather than silently reinterpret.

Base UOM replacement after inventory history is high-risk and cannot reinterpret historical base quantities. Exact migration/change procedure belongs P3/implementation and must preserve conversion lineage.

## 4. Barcode mapping

Create:
1. choose namespace/type;
2. normalize/validate format for that type;
3. select Product/Variant;
4. select UOM/package mapping if applicable;
5. uniqueness/ambiguity check;
6. activate.

Scan invariant:
one active code in a namespace/company resolves to one Product-or-Variant/UOM mapping.

GS1:
- GTIN maps to the specific trade item/package identity according to current GS1 rules.
- a different variant/package requiring a distinct trade identity cannot reuse another trade item's GTIN.

Internal barcode:
- Mars-defined mapping; still cannot be ambiguous.

Deactivate:
- future scan lookup excluded;
- history unchanged.

## 5. Product/category lifecycle

Category:
- create/edit normalized category;
- optional parent category;
- cycle prevention;
- assign/unassign Product relationships;
- optional primary category.

Category change has no stock/document posting effect.

## 6. Product / Variant deactivate-reactivate

ACTIVE → INACTIVE:
- explicit permission and audit;
- blocks new normal commercial selection;
- does not zero stock;
- does not cancel Reservations/documents;
- does not mutate history.

Existing stock:
- remains ledger truth.
- authorized Warehouse/Inventory disposition/transfer/count/reversal processes may still reference identity as needed to resolve physical stock.

INACTIVE → ACTIVE:
- rerun code/barcode/tracking validity checks;
- audit.

Capability changes:
- SELLABLE/PURCHASABLE future eligibility can change with audit.
- STOCKABLE/tracking changes are blocked when incompatible current stock/reservation/open physical processes exist.
- historical documents remain immutable.

## 7. Warehouse master lifecycle

Warehouse create:
- company scope;
- code/name;
- ACTIVE state.

Warehouse ACTIVE → INACTIVE:
- no new normal inventory operations.
- cannot erase inventory history.
- unresolved physical stock/reservations/open operations must be handled by accepted Warehouse workflow before completion.

No Product quantity is stored on Warehouse master itself.

## 8. Location lifecycle

Create:
- parent Warehouse;
- optional parent Location;
- code/name;
- stock-bearing/selectable flag;
- ACTIVE.

Rules:
- no hierarchy cycles.
- stock-bearing location is actual physical quantity dimension.
- aggregate parent is not quantity truth unless explicitly stock-bearing.
- inactive location unavailable for new placement.
- history remains.

## 9. Inventory disposition/status

Physical states:
AVAILABLE, QUARANTINE, QUALITY_HOLD, REWORK, DAMAGED, TRANSIT.

Status change:
- is a physical inventory effect when quantity moves from one disposition bucket to another;
- must be represented by the authoritative Inventory workflow/ledger effect, not Product master edit.

Reservation:
- separate non-physical overlay;
- never modeled as physical RESERVED status.

Scrap:
- pending damaged/hold quantity remains physical until an authorized scrap/disposal posting removes it from on-hand;
- posting history is immutable/reversible by accepted compensating workflow.

## 10. Quantity projections

At any requested dimensional scope:

`on_hand = net posted physical inventory ledger quantity`

`available_on_hand = net posted quantity currently in AVAILABLE disposition`

`reserved = net active reservation commitment`

`available_to_reserve = available_on_hand - reserved`

Rules:
- all in normalized base quantity for comparison;
- entered UOM/conversion retained where relevant;
- projections can be cached but ledger/reservations remain authority;
- master edit cannot directly modify any quantity.

## 11. Reservation

Create/release follows owning Inventory/Sales workflow.

PLAN-004 invariants:
- stockable identity only;
- non-physical;
- exact Product/Variant and quantity;
- warehouse/scope relation where required;
- base normalization deterministic;
- cannot deliberately reserve above eligible available-to-reserve under normal flow;
- concurrency guarantee belongs P3/implementation.

## 12. Lot lifecycle

For LOT or LOT_SERIAL tracked identity:
- physical receipt/creation introduces lot reference;
- lot code + Product/Variant/company resolves unambiguously;
- ledger movement carries lot;
- quantity remains ledger-derived;
- manufacture/expiry may be recorded where required.

Expiry:
- expired lot is not normal AVAILABLE-to-pick.
- FEFO is default recommendation for expiry-tracked eligible stock.
- manual exception policy is PLAN-006.

Lot correction:
- posted history not silently rewritten; use accepted correction/reversal workflow.

## 13. Serial lifecycle

For SERIAL or LOT_SERIAL:
- each physical instance has serial identity;
- each posted movement identifies serial;
- one serial instance is not simultaneously in two physical states/locations;
- movement quantity per serial instance is one base unit;
- source/target history reconstructs current position/status.

Transfer/reversal:
- new/compensating ledger history;
- no silent serial location update.

## 14. Historical snapshot consumption

Sales/Purchasing/Warehouse document:
1. selects eligible current Product/Variant/UOM;
2. resolves barcode/package if scanned;
3. freezes required Product Code/name/Variant/UOM/conversion and lot/serial references at workflow-defined point;
4. later master changes do not update the frozen snapshot.

## 15. Error/conflict model

Explicit conflicts:
- duplicate Product/Variant code;
- ambiguous barcode;
- stale Product/UOM conversion edit;
- stockability/tracking change with incompatible current stock/reservations;
- inactive Product/Variant/UOM/barcode selection;
- cross-company reference;
- invalid location hierarchy;
- lot/serial mismatch;
- serial already physically present elsewhere;
- expired lot normal-pick attempt;
- concurrent Reservation/physical posting over eligible quantity.

No conflict is solved by direct stock field overwrite.
