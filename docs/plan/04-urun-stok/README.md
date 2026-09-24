# Product / Inventory Master Module Plan

Status: PLAN-004 COMPLETED / FROZEN — planning only.

## Purpose

Products owns the live master definition of goods/services, variants, UOM assignments, barcodes and classification.
Inventory owns physical quantity truth, warehouse/location/status dimensions, reservation references and lot/serial traceability.

Canonical model:

Product
→ optional Variant(s)
→ Product UOM(s)
→ Barcode(s) / external identifiers
→ classification

Stockable trade identity
→ Warehouse
→ Location
→ Inventory disposition/status
→ optional Lot / Serial
→ Inventory Ledger

## Process ownership

- Products: product identity, lifecycle, capabilities, variant, product-UOM conversion, barcode mapping, category/classification and external product mappings.
- Inventory: authoritative physical quantity ledger, inventory status/disposition, lot/serial movement identity and reservation references.
- Warehouse: operational receiving/put-away/picking/transfer/count workflows in PLAN-006; PLAN-004 freezes only the required master/ledger boundaries.
- Sales: consumes eligible sellable Product/Variant/UOM and freezes historical snapshots.
- Purchasing: later consumes eligible purchasable Product/Variant/UOM.
- Finance/Costing: owns financial inventory valuation/cost method and monetary valuation truth; Product master does not own authoritative current cost/value.
- Settings/Numbering: exact Product/SKU code series/format where configured.

## Frozen invariants

- Product is company-scoped live master; no silent cross-company Product sharing in PLAN-004.
- Product kind is GOODS or SERVICE.
- Capabilities are explicit: SELLABLE, PURCHASABLE and STOCKABLE; SERVICE cannot be STOCKABLE.
- A Variant is optional Product-specific trade/stock identity for meaningful differentiators such as size/colour/configuration.
- Base UOM is the authoritative Product quantity basis.
- Alternate Product UOM conversions are positive decimal and deterministic; posted movements/documents snapshot the conversion used.
- Barcode lookup must resolve unambiguously to one active company trade identity/UOM/package mapping.
- GS1 GTIN, when used, is treated as a unique trade-item identifier; a materially different variant/package requires its own trade-item identity under GS1 rules.
- Physical inventory truth is derived from posted inventory ledger movements, never Product.stock_quantity.
- Reservation is a separate non-physical commitment; RESERVED is not a physical stock disposition.
- Location and inventory status/disposition are separate dimensions.
- AVAILABLE on-hand is distinct from reserved and available-to-reserve quantity.
- Lot/Serial tracking policy is explicit per stockable trade identity before movements that require it.
- Posted lot/serial/warehouse/location movement history is not silently edited or deleted.
- Live Product/UOM/barcode changes never rewrite frozen Sales/Purchasing document snapshots.
- Inactive Product/Variant/UOM/barcode is unavailable for new use by default while historical references remain readable.
- PostgreSQL/Inventory Ledger is authoritative; caches/search projections are rebuildable.

## External standards reference

GS1 is used only for GS1 identifiers:
- GTIN uniquely identifies a trade item.
- materially different trade items/variants/package levels require separate GTIN allocation according to current GS1 rules.
- serialisation distinguishes an individual instance, commonly together with its trade-item identity.
- lot/batch identifies a group for traceability and is not itself the trade-item class identity.

Non-GS1 internal barcodes remain Mars mappings and must still resolve unambiguously within company scope.

## Files

- plan.md — frozen Product/Inventory master decisions and boundaries.
- workflows.md — master lifecycle, UOM/barcode, inventory/status/lot/serial workflows.
- forms.md — list/detail/lookup/stock visibility UX.
- data-contract.md — conceptual entities, cardinality and source-of-truth rules.
- permissions.md — master/warehouse/inventory-sensitive planning permissions.
- integrations.md — external product/barcode mappings and master-data events.
- reports.md — stock/product read-model semantics.
- acceptance-criteria.md — PLAN-004 completion evidence.
- full-test-day.md — deferred heavy-test risks.

## Out of scope

PLAN-004 does not define:
- physical SQL schema/migrations;
- C#/API/TypeScript implementation;
- Purchase Order/Goods Receipt workflow;
- full Warehouse receiving/picking/count workflow;
- exact financial costing/valuation method;
- exact negative-stock exception policy;
- safety-stock/reorder/MRP formulas;
- exact Product/SKU numbering string format;
- provider-specific product sync behavior;
- technical files/attributes without an accepted later requirement.


## P5 implementation status

### PRODUCT-IMP-001 — Product Master Completion Tranche
Status: COMPLETED.

Readiness:
- docs/plan/04-urun-stok/p5-product-master-completion-readiness.md

Canonical implementation:
- docs/plan/04-urun-stok/product-imp-001-implementation.md

Verification:
- tested commit ac92911a8f5fcda070622b084216a43a70ad7d77
- Foundation Build 35981641268 — SUCCESS
- Foundation Test Deploy 35981641137 — SUCCESS
- frontend 19 / 19
- Foundation 66 / 66
- generated Product migration applied to TEST
- migration count 7
- EF pending-model PASS
- TEST /products 200
- live/ready 200 / 200
- protected Product routes 401 unauthenticated
- smoke PASS

Completed Product master surface:
- Product core/list/detail/edit/lifecycle
- UOM/Product-UOM
- Variant
- Barcode
- Category/Product Category
- Product External Mapping

Still deferred:
- Base UOM replacement
- post-use STOCKABLE/tracking transitions
- UOM transaction fraction/scale policy
- generic Variant EAV
- provider/marketplace/GS1 behavior

Inventory Ledger, Reservation, Lot/Serial physical truth, Warehouse operations and Finance/Costing valuation remain separate future authorities.

P5 next module direction:
- Inventory broad implementation tranche definition.
