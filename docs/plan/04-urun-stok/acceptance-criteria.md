# PLAN-004 Acceptance Criteria

Status: COMPLETED / FROZEN — planning only.

## 1. Product identity

- [x] Product is company-scoped authoritative live master.
- [x] GOODS / SERVICE distinction is explicit.
- [x] SELLABLE / PURCHASABLE / STOCKABLE capabilities are explicit.
- [x] SERVICE cannot be STOCKABLE.
- [x] optional Variant semantics are defined.
- [x] Product Code and Variant/SKU code ownership/uniqueness intent are defined.
- [x] Product ACTIVE / INACTIVE behavior is defined.

## 2. UOM / barcode / classification

- [x] exactly one current Base UOM is authoritative for Product quantity normalization.
- [x] alternate UOM conversion direction is deterministic.
- [x] decimal quantity/conversion semantics are explicit.
- [x] posted conversion snapshots cannot be reinterpreted by live master change.
- [x] barcode mapping resolves unambiguously to Product/Variant/UOM.
- [x] GS1 GTIN boundary and trade-item uniqueness semantics are documented.
- [x] category/classification is normalized; no comma-separated/EAV escape is introduced.
- [x] external product identifiers are mappings, not canonical Product identity.

## 3. Inventory truth

- [x] Inventory Ledger is authoritative for physical quantity.
- [x] Product/Warehouse/Location mutable stock fields are forbidden as authority.
- [x] on-hand, available physical, reserved and available-to-reserve are separately defined.
- [x] Reservation remains non-physical.
- [x] RESERVED is not used as a physical disposition duplicate.
- [x] status and Location are separate dimensions.
- [x] physical status semantics include AVAILABLE and accepted unavailable/transit dispositions.
- [x] scrap/disposal is treated as physical effect, not a permanent fake on-hand state.

## 4. Warehouse / location / lot / serial

- [x] Warehouse is company-scoped and has lifecycle.
- [x] Location belongs to Warehouse and optional hierarchy/cycle rules are defined.
- [x] stock-bearing vs aggregate Location is explicit.
- [x] tracking strategies NONE / LOT / SERIAL / LOT_SERIAL are defined.
- [x] lot quantity is ledger-derived.
- [x] serial instance uniqueness/current-position invariant is defined.
- [x] serial fractional movement is forbidden.
- [x] expiry and FEFO implications are defined without inventing override tolerance.
- [x] tracking-policy change risk is explicit.

## 5. Historical / accounting boundaries

- [x] live Product/Variant/UOM/barcode changes do not mutate historical document snapshots.
- [x] frozen Sales Product/UOM snapshot contract remains valid.
- [x] Sales Dispatch remains physical Sales STOCK OUT.
- [x] Sales Invoice does not second-post stock.
- [x] Product master does not own authoritative inventory value/average cost/COGS.
- [x] valuation/costing method is delegated to Finance/Costing.
- [x] no unsupported valuation formula is invented.

## 6. Permissions / integration / reports

- [x] Product/Variant/UOM/barcode permission boundaries are defined.
- [x] Warehouse/Location master permissions are separate from physical posting.
- [x] Product master events/external mapping boundaries are defined.
- [x] cache/projection cannot become inventory authority.
- [x] Product and stock reporting grains/formulas are defined.
- [x] Full Test Day backlog contains duplicate, concurrency, UOM, barcode, lot/serial, scope and snapshot risks.

## 7. Explicitly delegated non-blocking details

These are not material PLAN-004 blockers because the Product/Inventory core contract does not depend on an exact value:

- exact Product/SKU code format/series → Settings/Numbering.
- exact decimal quantity scale/fraction catalog → UOM + P3 implementation, constrained to decimal deterministic semantics.
- negative-inventory exception/approval → PLAN-006 Warehouse/Inventory operations.
- safety stock/reorder/ATP/MRP formulas → later Warehouse/MRP/Planning.
- inventory valuation/cost method → Finance/Costing.
- FEFO override/tolerance → PLAN-006 Warehouse.
- Purchase receiving tolerance → PLAN-005 Purchasing.
- technical product attributes/files/media → later explicit Product/Commerce/File requirement.
- provider-specific product sync → PLAN-009 Commerce/integration.

## 8. Fast verification checklist

Before PLAN-004 handoff:
- all ten Product/Inventory planning files exist and are non-empty;
- no empty placeholder remains;
- Product/Variant/UOM/barcode ownership is consistent;
- Product.stock_quantity/current stock authority is absent;
- Inventory Ledger authority is explicit;
- Reservation remains non-physical;
- status vs Location vs Reservation separation is consistent;
- historical Sales snapshots remain immutable;
- lot/serial rules are consistent;
- no SQL/migration/C#/TypeScript implementation was added;
- final main HEAD is reverified.

## 9. Exit condition

PLAN-004 Product / Inventory master planning is frozen.

Next repository-defined work package:
PLAN-005 — Purchasing workflow contract.

PLAN-005 content starts only in a separate task/session after state/handoff update.
