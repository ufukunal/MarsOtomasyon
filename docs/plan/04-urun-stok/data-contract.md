# Product / Inventory Master Conceptual Data Contract

Status: FROZEN logical/domain planning only. No physical SQL schema, column type or index is defined here.

## 1. Authority model

Products authoritative:
- Product live master;
- Product kind/capabilities/state;
- Variant master;
- Product-UOM assignments/conversions;
- Product/Variant codes;
- barcode/external product mappings;
- product classification/category relationships.

Inventory authoritative:
- physical Inventory Ledger movements;
- Reservation records;
- Warehouse/Location master needed for physical scope;
- lot/serial identity and movement lineage;
- physical disposition/status of posted quantity.

Finance/Costing authoritative:
- inventory financial valuation;
- cost layers/method;
- financial COGS/value truth.

Consuming documents authoritative for:
- historical Product/Variant/UOM/code/name/conversion/lot/serial snapshots required by that workflow.

Derived/projection:
- on-hand;
- available physical;
- reserved;
- available-to-reserve;
- warehouse/location/status/lot/serial balances;
- current inventory valuation reports;
- search indexes and dashboards.

Forbidden master authority:
- Product.stock_quantity;
- Product.available_quantity;
- Product.inventory_value;
- Product.average_cost as authoritative valuation truth;
- Warehouse.current_stock;
- Location.current_stock.

## 2. Conceptual entities

### Product

One company-scoped goods/service master.

Conceptual attributes:
- company;
- Product Code;
- name/description;
- kind GOODS/SERVICE;
- capabilities SELLABLE/PURCHASABLE/STOCKABLE;
- Base UOM reference;
- tracking strategy;
- ACTIVE/INACTIVE;
- audit/concurrency metadata.

### Variant

Optional child trade/stock identity of Product.

- inherits company/Product boundary;
- differentiates an operationally meaningful configuration;
- may have Variant/SKU Code;
- may participate in Product-UOM/barcode/tracking relationships;
- state ACTIVE/INACTIVE.

Variant does not create a second unrelated Product authority.

### UOM

Controlled measurement-unit definition.

Product UOM relation owns:
- Product/Variant applicability;
- Base vs alternate role;
- conversion factor to Product Base UOM;
- state;
- accepted operational metadata.

Conversion orientation:
`1 alternate = factor × base`.

### Barcode Mapping

Maps a normalized scanned/external code to one company trade identity.

Conceptually:
- company;
- namespace/type;
- value;
- Product/Variant;
- Product UOM/package identity;
- state;
- external verification metadata where applicable.

One active code mapping must be unambiguous within its namespace/company scope.

### Category

Normalized classification master.

Relations:
- Product many-to-many Category where configured;
- optional one primary Product category;
- optional parent Category;
- no hierarchy cycles.

Exact taxonomy/content is master data, not schema logic.

### Warehouse

Company-scoped physical/logical inventory facility.

- ACTIVE/INACTIVE;
- contains Locations;
- no mutable authoritative stock total.

### Location

Belongs to one Warehouse.

- optional parent Location;
- stock-bearing/selectable flag;
- ACTIVE/INACTIVE;
- physical/logical placement identity.

Hierarchy aggregate is not quantity authority.

### Inventory Status / Disposition

Controlled physical disposition semantics:
- AVAILABLE;
- QUARANTINE;
- QUALITY_HOLD;
- REWORK;
- DAMAGED;
- TRANSIT.

Reservation is not part of this physical status enum/authority.

Future statuses require explicit physical/availability semantics.

### Inventory Ledger Movement

Authoritative append-oriented physical quantity effect.

Conceptual dimensions as applicable:
- company;
- Product/Variant;
- quantity and UOM/base-normalized quantity;
- Warehouse;
- Location;
- status/disposition;
- lot;
- serial;
- source document/type/line;
- posting time;
- reversal/original linkage;
- actor/audit/correlation.

Exact physical schema belongs P3.

### Reservation

Authoritative non-physical commitment.

Conceptually references:
- company;
- source document/line;
- Product/Variant;
- Warehouse/scope;
- quantity/UOM/base-normalized quantity;
- lifecycle.

Reservation changes available-to-reserve, not physical on-hand.

### Lot

Batch/group traceability identity for LOT or LOT_SERIAL products.

Conceptually:
- company;
- Product/Variant;
- lot code;
- optional manufacture date;
- optional expiry date;
- metadata/state.

Lot quantity is ledger-derived.

### Serial

Individual instance traceability identity for SERIAL or LOT_SERIAL products.

Conceptually:
- company;
- Product/Variant;
- serial value;
- optional Lot;
- lifecycle derived from movement history.

Current physical position/status is a projection from Inventory Ledger.

### Product External Mapping

Maps provider/supplier/channel identity to Mars Product/Variant.

- source/provider/account context;
- external ID/SKU;
- Product/Variant;
- state/audit.

External IDs never replace canonical Mars identity.

## 3. Cardinality intent

- Company 1 → many Products.
- Product 1 → zero/many Variants.
- Product 1 → one Base UOM relation and zero/many alternate Product-UOM relations.
- Product/Variant 1 → zero/many Barcode mappings.
- Product many ↔ many Categories where configured.
- Company 1 → many Warehouses.
- Warehouse 1 → many Locations.
- Location 0/1 parent → many child Locations.
- Product/Variant 1 → zero/many Lots.
- Product/Variant 1 → zero/many Serials.
- Product/Variant/warehouse scope → many Reservations.
- Inventory Ledger → many append-only movements.

P3 chooses exact relational shape without changing these ownership semantics.

## 4. Uniqueness intent

Future durable constraints must enforce:

- Product Code unambiguous within company/code namespace.
- active Variant/SKU Code unambiguous within accepted company/code namespace.
- active barcode mapping unambiguous within company + barcode namespace.
- GS1 GTIN follows current GS1 uniqueness/allocation rules.
- Category hierarchy cannot cycle.
- Warehouse/Location codes must be unambiguous within their accepted scope.
- Lot code unambiguous within company + Product/Variant lot scope.
- Serial value identifies one instance within company + Product/Variant serial scope.
- same serial instance cannot have two simultaneous authoritative physical positions.
- external mapping unique within provider/account/system context.

Exact normalization, case folding and indexes belong P3.

## 5. Quantity semantics

All comparable physical quantities normalize to Base UOM using accepted decimal conversion snapshot.

Authoritative inputs:
- posted Inventory Ledger movements;
- active Reservations.

Derived:
- `on_hand` = net posted physical quantity.
- `available_on_hand` = net posted physical quantity in AVAILABLE status.
- `reserved` = net active Reservation commitment.
- `available_to_reserve` = available_on_hand - reserved.

Dimensions may include:
company → Product/Variant → Warehouse → Location → status → Lot → Serial.

A projection may aggregate upward but does not become source-of-truth.

## 6. Status and location separation

Physical location answers "where".
Inventory disposition answers "what operational condition".

Examples:
- Warehouse A / Bin B12 / AVAILABLE.
- Warehouse A / QA-01 / QUARANTINE.
- TRANSIT may use transfer/transit context without pretending it is a normal source Warehouse AVAILABLE balance.

No single text/status field combines location and disposition as authoritative truth.

## 7. Reservation separation

Reservation:
- no physical movement;
- no change to on-hand;
- separate lifecycle/source link.

Forbidden:
- materializing Reservation by moving ledger quantity into a physical RESERVED status solely to represent commitment.

If future operations physically stage/pick stock, that physical operation needs its own accepted Warehouse status/location/movement semantics.

## 8. Lot / Serial rules

Tracking strategy:
- NONE
- LOT
- SERIAL
- LOT_SERIAL

LOT:
- movement carries Lot when tracking required;
- quantity can be greater than one;
- optional expiry/manufacture metadata.

SERIAL:
- movement identifies each individual instance;
- quantity per serial movement is one base unit;
- no simultaneous duplicate physical presence.

LOT_SERIAL:
- Serial may reference Lot; both are carried by required physical movements.

Changing tracking strategy with existing incompatible stock/history is blocked until an explicit safe transition plan exists.

## 9. Historical snapshots

Posted/frozen documents may snapshot:
- Product/Variant codes and names;
- UOM code/name;
- accepted conversion factor/direction;
- barcode/GTIN where needed;
- lot/serial;
- downstream tax/price/cost values owned by that document.

Master changes do not mutate snapshots.

Historical snapshot is intentional denormalization and not a master duplication bug.

## 10. Cost / valuation boundary

Quantity truth and valuation truth are separate.

PLAN-004 does not select FIFO, weighted-average, standard, specific identification or another valuation method.

Requirements for later Finance/DB planning:
- Product master cannot be sole source of current value/cost.
- valuation must be traceable to authoritative financial/cost records.
- Sales COGS recognition point remains Invoice POST under frozen Sales policy.
- lot/serial may become valuation dimensions only if later costing policy explicitly requires it.

## 11. Company / branch / warehouse scope

Product:
- company-scoped.

Variant/Product-UOM/barcode/category relation:
- belongs to Product company context unless later explicitly globalized.

Warehouse:
- company-scoped.

Location:
- inherits Warehouse company.

Branch:
- not Product identity scope by default.
- consuming workflow may apply branch visibility/numbering later.

Cross-company physical ledger/reservation/product references are forbidden.

## 12. Concurrency risks for P3

Future durable design must protect:
- duplicate Product/SKU code creation;
- duplicate/ambiguous barcode mapping;
- concurrent UOM factor edit vs transaction creation;
- stale Product capability/tracking change;
- concurrent Reservation;
- concurrent physical post;
- same serial posted to two locations;
- concurrent lot/serial receipt;
- Warehouse/Location deactivate vs active operation;
- duplicate external mapping/event.

Potential locking/version/constraint strategy belongs P3.

## 13. Forbidden data models

- authoritative Product.stock_quantity.
- authoritative Warehouse/Location stock total columns.
- inventory value/average cost treated as casual Product master truth.
- comma-separated barcode/UOM/category/lot/serial IDs.
- uncontrolled EAV as default variant/attribute storage.
- physical RESERVED status used to duplicate Reservation commitment.
- provider SKU/GTIN used as internal PK/source-of-truth.
- posted movement update/delete to "fix" stock.
