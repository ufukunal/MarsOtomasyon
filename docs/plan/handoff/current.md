# Current Handoff

## Repository

- Repository: ufukunal/MarsOtomasyon
- Allowed branch: main
- Current HEAD: verify from Git at session start
- Master plan: docs/plan/master-project-plan.md
- Session protocol: docs/ai/session-execution-protocol.md

## Current phase

P2 — Core Commercial Workflow Planning

## Completed predecessor

PLAN-004 — Product / Inventory master model

Status: COMPLETED / FROZEN

Primary completion evidence:
- `96c1855f200512d50009d1499aeaf7e4f96d88bb` — PLAN-004 acceptance criteria completed after Product / Inventory master contracts were frozen.

Product / Inventory planning contracts:
- docs/plan/04-urun-stok/README.md
- docs/plan/04-urun-stok/plan.md
- docs/plan/04-urun-stok/workflows.md
- docs/plan/04-urun-stok/forms.md
- docs/plan/04-urun-stok/data-contract.md
- docs/plan/04-urun-stok/permissions.md
- docs/plan/04-urun-stok/integrations.md
- docs/plan/04-urun-stok/reports.md
- docs/plan/04-urun-stok/acceptance-criteria.md
- docs/plan/04-urun-stok/full-test-day.md

No SQL schema/migration, application code, deployment or heavy tests were created/run by PLAN-004.

## Frozen Product / Inventory decisions

- Product is company-scoped authoritative live master.
- Product kind is GOODS or SERVICE.
- SELLABLE / PURCHASABLE / STOCKABLE capabilities are explicit; SERVICE cannot be STOCKABLE.
- Variant is optional and represents an operationally meaningful Product-specific trade/stock identity.
- Base UOM is the authoritative quantity normalization basis.
- alternate Product-UOM conversions are positive decimal and historically snapshotted.
- barcode lookup must resolve unambiguously to one active Product/Variant/UOM/package mapping.
- GS1 GTIN is treated as an external trade-item identifier; materially different trade items require distinct GTIN under current GS1 rules.
- Product/Warehouse/Location mutable stock totals are not authoritative.
- Inventory Ledger is authoritative physical quantity truth.
- Reservation is non-physical and is not a physical RESERVED status.
- Location and physical inventory status/disposition are separate dimensions.
- physical dispositions frozen for current planning are AVAILABLE, QUARANTINE, QUALITY_HOLD, REWORK, DAMAGED and TRANSIT.
- on-hand, available physical, reserved and available-to-reserve are separate derived quantities.
- tracking strategies are NONE / LOT / SERIAL / LOT_SERIAL.
- lot quantity and serial current position derive from posted Inventory Ledger history.
- expired lot is not normal AVAILABLE-to-pick; FEFO is the default recommendation where expiry tracking applies.
- live Product/Variant/UOM/barcode changes never rewrite historical document snapshots.
- Product master does not own authoritative inventory valuation/current cost/COGS truth; Finance/Costing owns valuation policy.
- cross-company Product sharing is not introduced.

## Preserved upstream dependencies

Sales:
- Reservation remains manual/non-physical.
- Dispatch POST is physical Sales STOCK OUT.
- Sales Invoice does not post stock.
- Product/UOM snapshots remain immutable.

Party:
- Supplier and Customer are Party roles.
- Product/Purchasing must not duplicate Party identity truth.

## Explicit non-blocking delegations

- exact Product/SKU numbering format → Settings/Numbering.
- exact quantity decimal scale → UOM/P3, constrained to decimal deterministic semantics.
- negative-inventory exception policy → PLAN-006 Warehouse.
- safety stock/reorder/ATP/MRP → later planning.
- inventory valuation/cost method → Finance/Costing.
- FEFO override/tolerance → PLAN-006 Warehouse.
- receiving tolerance → PLAN-005 Purchasing.
- provider-specific product sync → Commerce/integration.

## Next safe work package

PLAN-005 — Purchasing workflow contract

Target:
`docs/plan/07-satinalma/`

PLAN-005 is READY but content work has not started in this handoff.

Before work:
- verify real main HEAD;
- read governance/master/state/handoff;
- read frozen Sales, Party and Product/Inventory contracts;
- inspect current 07-satinalma files;
- route skills through skill-router;
- produce CONTEXT RECEIPT.

Do not jump to Warehouse implementation, logical SQL schema, application code or Full Test Day.
