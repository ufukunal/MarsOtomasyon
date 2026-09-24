# Active Tasks

## P5 — Inventory broad implementation tranche definition
**Status:** SCOPE DEFINITION REQUIRED / NOT STARTED

Predecessor:
- PRODUCT-IMP-001 — COMPLETED
- canonical report: docs/plan/04-urun-stok/product-imp-001-implementation.md
- tested commit: ac92911a8f5fcda070622b084216a43a70ad7d77
- Foundation Build 35981641268 — SUCCESS
- Foundation Test Deploy 35981641137 — SUCCESS
- frontend 19 / 19
- Foundation 66 / 66
- migration count 7
- TEST /products 200
- live/ready 200 / 200
- smoke PASS

Direction:
- P5 order moves from Products to Inventory
- use frozen PLAN-004 Inventory contracts and PLAN-010
- define one broad coherent Inventory tranche
- do not split every Inventory capability into micro-packages merely for implementation convenience

Before implementation:
- inspect current Inventory source
- freeze Inventory authority and explicit exclusions
- distinguish Inventory from Warehouse operational workflows
- preserve Finance/Costing valuation authority
- map entities/constraints/migration/API/UI/permissions/idempotency/concurrency/tests
- assign an Inventory implementation package ID only after scope is explicit

Do not invent:
- mutable stock-total authority
- negative-stock exception
- lot/serial bypass
- reservation semantics
- Warehouse workflow ownership
- Finance valuation/current-cost/COGS authority

Planning:
- master 9 / 30 = 30.0%
- P2 8 / 8 = 100.0%
- separate repository inconsistency: docs/db/acceptance-criteria.md says 10 / 30 = 33.3%

Heavy tests remain Full Test Day only.
