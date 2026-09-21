# Product / Inventory Master Reporting Contract

Status: FROZEN PLAN-004 planning semantics.

Reports are read models/projections, not master/ledger authority.

## 1. Product Directory

Grain:
one Product or selectable Product/Variant row depending view.

Fields:
- company;
- Product Code;
- Variant/SKU Code;
- name;
- kind;
- SELLABLE/PURCHASABLE/STOCKABLE;
- Base UOM;
- category;
- tracking strategy;
- ACTIVE/INACTIVE.

Filters:
- company;
- capability;
- state;
- category;
- tracking strategy;
- code/name/barcode.

Source:
Product authoritative master + rebuildable search projection.

## 2. Inventory Status / Stock Inquiry

Required dimensions/filters:
- company;
- Product/Variant;
- Warehouse;
- Location;
- disposition/status;
- Lot;
- Serial where applicable.

Measures:
- on_hand;
- available_on_hand;
- reserved;
- available_to_reserve.

Sources:
- physical quantity: Inventory Ledger.
- commitment: Reservation authority.

Never source current stock from Product master.

## 3. Quantity formulas

At requested compatible base-UOM scope:

`on_hand = SUM(net posted physical ledger base quantity)`

`available_on_hand = SUM(net posted ledger base quantity where disposition = AVAILABLE)`

`reserved = SUM(net active reservation base quantity)`

`available_to_reserve = available_on_hand - reserved`

Reversal/compensating movements are included through net authoritative history.

Exact negative-stock exception presentation belongs later Warehouse policy.

## 4. Warehouse / Location balance

Grain:
Product/Variant × Warehouse × Location × status, optionally Lot/Serial.

Rules:
- aggregate parent Location is report roll-up, not separate duplicate quantity.
- TRANSIT shown separately from normal Warehouse AVAILABLE.
- QUARANTINE/HOLD/REWORK/DAMAGED shown as on-hand but unavailable.

## 5. Lot / Expiry report

Grain:
Product/Variant × Lot × Warehouse/Location/status.

Fields:
- lot;
- manufacture date if known;
- expiry date if known;
- derived on-hand;
- availability state;
- expired/near-expiry classification when policy later defines threshold.

FEFO:
- operational recommendation only.
- report must not rewrite stock or imply a movement.

Exact "near expiry" day threshold is not invented in PLAN-004.

## 6. Serial trace

Grain:
one serial instance.

Show:
- Product/Variant;
- Serial;
- Lot if any;
- current derived Warehouse/Location/status;
- movement timeline;
- source documents.

Current state must be reconstructed from authoritative posted history/projection.

## 7. Barcode report

Grain:
active/inactive barcode mapping.

Fields:
- namespace/type;
- code;
- Product/Variant;
- UOM/package;
- state;
- external verification metadata.

Duplicate/ambiguity report:
- any conflicting mapping is an exception requiring correction before normal scan use.

## 8. Product data-quality report

May include:
- missing Base UOM;
- invalid SERVICE+STOCKABLE combination;
- duplicate/ambiguous code/barcode candidate;
- inactive barcode used by open draft;
- stockable Product missing accepted tracking setup where required;
- invalid category hierarchy;
- inactive Warehouse/Location with unresolved physical quantity.

This is an operational control queue, not authority.

## 9. Cost / valuation reporting boundary

PLAN-004 does not define authoritative inventory value, average cost or gross valuation formulas.

Future Finance/Costing reports must source accepted valuation authority, not Product reference fields.

Sales COGS remains governed by frozen Sales contract.

## 10. Historical snapshots

Historical Sales/Purchasing reports use document snapshot for:
- Product Code/name;
- Variant;
- UOM/conversion;
- commercial fields.

They must not silently substitute today's Product master text when historical accuracy is required.

## 11. Access

Reports respect:
- company scope;
- Product read;
- Warehouse/Inventory stock read;
- future valuation permissions for financial values.

Export applies same permissions and cannot expose another company's stock.
