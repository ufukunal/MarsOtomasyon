# Product / Inventory Master Domain Plan

Status: COMPLETED / FROZEN — PLAN-004

## 1. Business objective

Provide one deterministic Product/Inventory master contract so Sales, Purchasing, Warehouse, Finance and external channels can identify the same item/UOM/barcode while physical inventory remains ledger-derived and historically traceable.

Expected outcomes:
- no duplicate stock truth on Product master;
- deterministic Product/Variant/UOM/barcode lookup;
- warehouse/location/status dimensions separated;
- lot/serial traceability available when required;
- inactive/master changes do not corrupt historical documents;
- future Purchasing/Warehouse/DB work can proceed without inventing core Product semantics.

## 2. Scope

In:
- Product identity and lifecycle;
- GOODS / SERVICE distinction;
- SELLABLE / PURCHASABLE / STOCKABLE capabilities;
- optional Variants;
- base/alternate UOM;
- decimal UOM conversions;
- Product/SKU code ownership;
- barcode/GTIN mapping and uniqueness;
- categories/classification;
- Warehouse and Location master boundary;
- inventory disposition/status semantics;
- on-hand / reserved / available distinction;
- Inventory Ledger authority;
- lot / serial / expiry traceability boundary;
- historical Product snapshots;
- duplicate/inactive behavior;
- permissions/integrations/reporting planning.

Out:
- SQL/migrations;
- application/API/UI implementation;
- Goods Receipt/Picking/Transfer/Count workflow detail;
- costing method (FIFO/weighted-average/standard/etc.);
- landed-cost allocation;
- safety stock/reorder/MRP;
- negative-inventory exception approval;
- exact SKU/code format;
- provider-specific synchronization.

## 3. Sources

Repository:
- `docs/db/00-domain-dictionary.md`: Product is sellable, purchasable, stockable or otherwise managed; exact variant/UOM/barcode behavior was intentionally deferred to PLAN-004.
- `docs/db/01-design-principles.md`: ledger authority, no mutable product stock, decimal quantities, snapshot policy.
- frozen Sales contracts: Reservation non-physical, Dispatch physical STOCK OUT, Invoice no second stock effect, Product/UOM snapshots immutable.
- frozen Party contracts: company scope and live-master-vs-snapshot pattern.
- warehouse skill: location/status separation, reservation, barcode/lot/serial scan validation, transfer/count principles.
- accounting skill: cost/valuation and posted financial history must not be mutable Product truth.

External verification:
- GS1 GTIN uniquely identifies a trade item; materially different trade-item variants/package levels require distinct identifiers under current GTIN allocation rules.
- GS1 serialisation distinguishes an individual instance; lot/batch supports group traceability.

## 4. Frozen decisions

### PRODUCT-D001 — Company-scoped Product master

- Product is the authoritative live item/service master within one Mars company.
- Cross-company Product reference/sharing is not introduced in PLAN-004.
- Same real-world item used by another company is separately governed in that company unless a future explicit shared-master design is accepted.
- Branch and Warehouse are not Product identity scope.

### PRODUCT-D002 — Product kind and capabilities

Product kind:
- GOODS
- SERVICE

Capabilities:
- SELLABLE
- PURCHASABLE
- STOCKABLE

Rules:
- capability flags are explicit and may be combined where semantically valid.
- SERVICE must have STOCKABLE = false.
- non-stock GOODS are allowed; they may be sellable/purchasable without physical inventory truth.
- only STOCKABLE trade identities may participate in Reservation, physical Inventory Ledger, warehouse/location/status and lot/serial tracking.
- enabling/disabling STOCKABLE or changing tracking policy is a high-risk master change and cannot invalidate existing on-hand/reservation/open physical processes.

### PRODUCT-D003 — Product vs Variant

- Product is the common master/family identity.
- Variant is optional.
- Variant is used only when a differentiator changes the selectable trade/stock identity, e.g. size, colour or accepted configuration.
- Products without variants transact directly at Product identity.
- Products with active Variants transact at the specific Variant when the differentiator matters operationally.
- Variant cannot override company ownership or create a second unrelated Product master.
- exact variant attribute taxonomy is not frozen here; attributes must be normalized/source-backed when later required, not EAV-by-default.

### PRODUCT-D004 — Product/SKU code

- every Product has a canonical company-scoped Product Code.
- Variant may have an optional distinct SKU/Variant Code when needed for operational lookup.
- active Product Code and Variant Code must resolve unambiguously within their accepted company/code namespace.
- exact format/series is Settings/Numbering-owned.
- code change is audited and cannot rewrite historical document snapshots or external mappings implicitly.
- code reuse that would make historical lookup ambiguous is forbidden.

### PRODUCT-D005 — UOM authority

- every Product has exactly one current Base UOM for quantity normalization.
- a Product may allow multiple alternate UOMs.
- Product-UOM conversion orientation is deterministic: `1 alternate UOM = conversion_factor × Base UOM`.
- conversion_factor must be positive decimal.
- quantity arithmetic uses decimal/NUMERIC semantics; float/double forbidden.
- exact allowed quantity scale/fraction policy is a UOM/Product policy input resolved before implementation; it cannot vary ad hoc by screen.
- posted/frozen documents and Inventory Ledger effects preserve entered quantity/UOM plus the accepted conversion-to-base snapshot needed for historical correctness.
- changing a live conversion affects future use only and must not reinterpret posted quantities.

### PRODUCT-D006 — Barcode / GTIN

Barcode mapping contains conceptually:
- company;
- namespace/type (GS1 GTIN or internal/other accepted type);
- normalized value;
- target Product or Variant;
- UOM/package identity where the barcode denotes a pack level;
- lifecycle/status.

Rules:
- an active scanned code must resolve to one trade identity/UOM mapping within its namespace/company scope.
- the same active Mars barcode value cannot map ambiguously to two Products/Variants.
- one Product/Variant may have multiple barcode mappings.
- packaging/UOM barcode may carry its Product-UOM conversion.
- GS1 GTIN is not treated as a parseable internal Product Code.
- materially different GS1 trade items/variants/package levels require distinct GTIN according to current GS1 rules.
- barcode deactivation affects future scanning only; historical documents remain via snapshots/source links.
- external barcode/GTIN verification does not make a provider the authoritative Product master.

### PRODUCT-D007 — Category/classification

- Categories are normalized classification masters, not comma-separated text.
- Product may have zero or more category relationships.
- one category may be designated primary for default navigation/reporting when configured.
- optional parent-child category hierarchy may be used; cycles are forbidden.
- category edit does not rewrite historical Product snapshots.
- technical attribute schema is not invented in PLAN-004; a later requirement must define typed ownership rather than generic uncontrolled EAV.

### PRODUCT-D008 — Product lifecycle

States:
- ACTIVE
- INACTIVE

ACTIVE:
- eligible for new use according to capabilities and child state.

INACTIVE:
- unavailable for new commercial selection by default;
- historical documents and ledger remain readable;
- existing stock is not deleted or zeroed;
- authorized Inventory/Warehouse actions needed to dispose/transfer/reconcile existing physical stock may still reference the inactive identity according to their workflow;
- reactivation requires permission/audit and uniqueness validation.

Product hard delete is not required for historically referenced items.

Variant/UOM/barcode also have ACTIVE/INACTIVE lifecycle independent of Product, but inactive Product blocks new normal selection of children.

### PRODUCT-D009 — Warehouse master

- Warehouse is company-scoped physical/logical inventory facility.
- Warehouse identity is not Product identity.
- Warehouse state ACTIVE/INACTIVE.
- inactive Warehouse cannot be selected for new normal inventory operations.
- deactivation must not erase on-hand/reservation/history; unresolved current stock/reservation/open-operation handling belongs Warehouse workflow and must be satisfied before final deactivation is accepted.
- exact receiving/picking/transfer/count processes belong PLAN-006.

### PRODUCT-D010 — Location master

- Location belongs to exactly one Warehouse.
- Location represents an addressable physical/logical position.
- optional parent-child hierarchy supports zones/aisles/bins; cycles are forbidden.
- a location explicitly declares whether it is stock-bearing/selectable for physical placement.
- aggregate/non-stock-bearing parent locations cannot become silent quantity truth.
- location ACTIVE/INACTIVE state affects future placement/selection only; history remains.
- location and inventory disposition/status are separate dimensions.

### PRODUCT-D011 — Physical inventory status/disposition

Frozen physical/disposition states required by current source contracts:
- AVAILABLE
- QUARANTINE
- QUALITY_HOLD
- REWORK
- DAMAGED
- TRANSIT

Rules:
- only AVAILABLE quantity is normal reservation/pick eligible.
- QUARANTINE / QUALITY_HOLD / REWORK / DAMAGED are physically on-hand where located but not normal available stock.
- TRANSIT is company-owned physical quantity in transfer context and not normal source-warehouse available stock.
- RESERVED is not a physical status; Reservation is a non-physical commitment overlay.
- SCRAP is not kept as permanent on-hand truth after a posted scrap/disposal movement; pending disposition remains in an accepted non-available status until the later Warehouse/Quality workflow posts the physical effect.
- adding future statuses requires an explicit ownership/effect rule; UI labels cannot create stock semantics.

### PRODUCT-D012 — Inventory quantity truth

Authoritative physical truth:
posted Inventory Ledger movements, including reversal/compensating movements.

Derived:
- on_hand_qty;
- available_on_hand_qty;
- reserved_qty;
- available_to_reserve_qty;
- warehouse/location/status balances;
- lot/serial balances.

Definitions:
- on_hand = net posted physical Inventory Ledger quantity for the requested company/product-or-variant/base-UOM dimensions, including non-available physical statuses where applicable.
- available_on_hand = net on-hand quantity in AVAILABLE disposition.
- reserved = net active non-physical Reservation commitment against eligible identity/scope.
- available_to_reserve = AVAILABLE on-hand - active Reservation quantity for the matching eligible scope.
- no mutable Product.stock_quantity/current_stock authority.
- routine reservation/dispatch must not intentionally over-process eligible quantity; exact negative-stock exception policy is deferred to Warehouse/Inventory planning and cannot be silently enabled.

### PRODUCT-D013 — Reservation boundary

- Reservation remains non-physical.
- Reservation links source document line to Product/Variant, warehouse/scope and quantity in accepted UOM/base normalization.
- Reservation does not change on-hand quantity or physical status.
- Dispatch/other physical posting consumes/releases reservation according to owning workflow.
- inventory status and Reservation must not be collapsed into a single `RESERVED` stock status.

### PRODUCT-D014 — Lot tracking

Tracking strategy per stockable trade identity:
- NONE
- LOT
- SERIAL
- LOT_SERIAL

Lot:
- identifies a batch/group for traceability.
- lot code is scoped to the Product/Variant trade identity plus company and must resolve unambiguously there.
- lot may carry manufacture/expiry metadata when relevant.
- lot quantity is ledger-derived; no mutable lot stock field authority.
- expiry-tracked lot that is expired is not normal AVAILABLE-to-pick inventory until an authorized later workflow resolves disposition.
- FEFO is the default picking recommendation for expiry-tracked eligible stock; it is a selection strategy, not a rewriting of ledger truth. Exact override/tolerance approval belongs PLAN-006.

### PRODUCT-D015 — Serial tracking

- serial identifies an individual physical instance.
- serial-tracked movement must identify each instance.
- one serial instance cannot be simultaneously on-hand in two locations/statuses.
- within a company + Product/Variant serial namespace, an active serial value identifies one instance.
- serial quantity semantics are unitary; fractional movement of one serial instance is invalid.
- serial lifecycle/location/status is reconstructed from posted Inventory Ledger history.
- reversal/transfer changes state through compensating/new movement history, not silent serial relocation.

### PRODUCT-D016 — Historical snapshot

Commercial/posted document snapshot may freeze as applicable:
- Product Code;
- Variant/SKU Code;
- Product/Variant name/description;
- selected UOM code/name;
- accepted UOM conversion;
- barcode/GTIN when legally/operationally relevant;
- tax/commercial fields owned by the document;
- lot/serial references on physical documents where required.

Live Product/Variant/UOM/barcode/category edits never rewrite frozen snapshots.

### PRODUCT-D017 — Cost / valuation boundary

- Product master does not own authoritative current inventory value, average cost or COGS truth.
- physical quantity ledger and financial valuation/costing are separate authoritative concerns.
- Sales frozen decision keeps financial COGS recognition at Sales Invoice POST.
- exact inventory valuation/cost method, cost layers and revaluation rules belong later Finance/Costing/DB planning.
- optional reference/standard cost cannot silently become valuation authority without an accepted Finance policy.

### PRODUCT-D018 — External mappings

- marketplaces/suppliers/external systems may map their product IDs/SKUs/GTINs to Mars Product/Variant.
- mapping is not canonical Product identity.
- duplicate external mapping must be idempotent/conflict-safe.
- provider-specific fields do not belong in generic Product core unless a source-backed shared contract exists.

## 5. Master/effect matrix

Master actions themselves have no physical/financial posting effect.

| Action | MASTER/DOC | RES | STOCK | ACCOUNT | CASH/BANK | COST |
|---|---|---|---|---|---|---|
| Create/edit Product/Variant | master | NONE | NONE | NONE | NONE | NONE |
| Add/edit UOM conversion | master | NONE | NONE | NONE | NONE | NONE |
| Add/deactivate Barcode | master | NONE | NONE | NONE | NONE | NONE |
| Category/classification change | master | NONE | NONE | NONE | NONE | NONE |
| Warehouse/Location master change | master | NONE | NONE | NONE | NONE | NONE |
| Inventory status master/config change | master/config | NONE | NONE | NONE | NONE | NONE |
| Reservation create/release | reference | INCREASE/DECREASE | NONE | NONE | NONE | NONE |
| Physical Inventory posting | source document/ledger | may consume/release | IN/OUT/TRANSFER/STATUS effect | NONE by itself | NONE | valuation per owning finance workflow |
| Master deactivate | state | NONE | NONE | NONE | NONE | NONE |

## 6. Cross-module contracts

Sales:
- consumes active SELLABLE Product/Variant/UOM.
- stockable Sales reservation/dispatch use Inventory authority.
- posted Sales documents keep immutable Product/UOM snapshots.
- Invoice does not second-post stock.

Purchasing:
- later consumes active PURCHASABLE Product/Variant/UOM.
- Goods Receipt physical STOCK IN belongs PLAN-005/006.
- Purchase Invoice cannot double-increase stock after Goods Receipt.

Warehouse:
- owns physical operational workflow and uses frozen warehouse/location/status/lot/serial master semantics.

Finance:
- owns valuation/cost/accounting policy.
- may consume Product classification/read data but cannot use Product mutable fields as valuation truth.

Commerce:
- external product identities map to Mars Product/Variant; provider IDs are not Product authority.

## 7. Explicit non-blocking delegations

These are owned by later plans and do not block PLAN-004 core freeze:
- exact Product/SKU numbering string/series → Settings/Numbering.
- exact quantity decimal scale/fraction policy per UOM → UOM/DB implementation decision constrained by decimal semantics.
- exact negative-inventory exception/approval → Warehouse/Inventory operational plan.
- safety-stock/reorder/ATP formula → Warehouse/MRP/Planning.
- valuation method and inventory-value cost layers → Finance/Costing.
- exact FEFO override/tolerance → Warehouse.
- Purchase receiving tolerance → Purchasing.
- technical attributes/files/media → later explicit Product/Commerce/File requirement.

## 8. Reviewer outcome

ERP: Product/Variant/UOM/barcode and stockable/non-stock/service boundaries are deterministic.
Warehouse: physical status, location, Reservation, lot/serial and ledger truth do not conflict.
Database: master relations are normalizable; no mutable Product stock/value authority introduced.
Architecture: Product identity, Inventory quantity and Finance valuation ownership are separated.
Developer: future implementation can enforce lifecycle/concurrency without schema-first assumptions.
Testing: duplicate barcode/code, UOM conversion, lot/serial, stale master and company isolation are testable.
UX: live master and stock projections/historical snapshots can be shown without editable false truth.
Accounting: Product master does not own valuation/COGS truth.
