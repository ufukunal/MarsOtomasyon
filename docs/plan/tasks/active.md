# Active Tasks

## INVENTORY-IMP-001 — Inventory Authority & Traceability Tranche
**Status:** READY FOR IMPLEMENTATION / NOT STARTED

Canonical readiness:
- docs/plan/04-urun-stok/p5-inventory-authority-traceability-readiness.md

Predecessor:
- PRODUCT-IMP-001 — COMPLETED
- canonical report: docs/plan/04-urun-stok/product-imp-001-implementation.md
- tested commit: ac92911a8f5fcda070622b084216a43a70ad7d77
- Foundation Build 35981641268 — SUCCESS
- Foundation Test Deploy 35981641137 — SUCCESS
- migration count 7

Broad included authority:
- Warehouse master and Location hierarchy/lifecycle
- fixed physical dispositions AVAILABLE / QUARANTINE / QUALITY_HOLD / REWORK / DAMAGED / TRANSIT
- Lot and Serial trace identity
- append-oriented Inventory Ledger physical quantity authority
- append-oriented non-physical Reservation authority
- Product/Variant/UOM/tracking enforcement
- stock/availability/movement/reservation/trace read surfaces
- permissions/API/Mars.Web/audit/idempotency/concurrency/targeted tests
- additive Inventory EF migration and TEST deployment

Explicit exclusions:
- Warehouse operational receiving/put-away/pick/pack/stage/load/transfer/count/replenishment/damage/scrap/offline workflows
- Sales/Purchasing/Returns source documents and state machines
- arbitrary public physical movement posting
- free-form Reservation mutation disconnected from Sales Order effective line/version
- mutable stock totals
- Finance valuation/current-cost/COGS
- Product Base UOM replacement and post-use STOCKABLE/tracking transitions
- production deployment
- Full Test Day

Implementation constraints:
- Inventory Ledger is the only physical quantity authority
- Reservation is non-physical; RESERVED is not a physical disposition
- normal posting cannot produce negative physical stock
- posted movement history is never silently updated/deleted
- reversal/compensation preserves original linkage
- Warehouse/Location permissions use later frozen PLAN-006 namespace
- TEST migration-safety must include Migrations/Inventory
- do not claim authenticated TEST Inventory mutation without direct evidence

Planning:
- master 9 / 30 = 30.0%
- P2 8 / 8 = 100.0%
- separate repository inconsistency: docs/db/acceptance-criteria.md says 10 / 30 = 33.3%

Heavy tests remain Full Test Day only.
