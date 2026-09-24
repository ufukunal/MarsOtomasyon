# Active Tasks

## SALES-IMP-001 — Sales Commercial & Fulfillment Authority Tranche
**Status:** READY FOR IMPLEMENTATION / NOT STARTED

Canonical readiness:
- docs/plan/05-satis/p5-sales-commercial-fulfillment-readiness.md

Predecessor:
- INVENTORY-IMP-001 — COMPLETED
- canonical report: docs/plan/04-urun-stok/inventory-imp-001-implementation.md
- tested commit: edcc24f1200b70aad102fc510ad7bec60c4515e7
- Foundation Build 35998315605 — SUCCESS
- Foundation Test Deploy 35998315582 — SUCCESS
- migration count 8

Broad included authority:
- Quote/revision/line lifecycle
- partial/repeated Quote conversion with durable cumulative cap
- Sales Order/effective version/line lifecycle
- controlled Order amendment
- approval evidence/SoD
- explicit Inventory Reservation create/increase/release integration
- Warehouse resource-scope authorization required for Dispatch
- Dispatch create/read/state
- atomic Dispatch POST with Inventory physical STOCK OUT and Reservation consume
- Dispatch reversal
- Sales Invoice DRAFT/source/calculation commercial authority
- optional Proforma inside the same SALES-IMP-001 package
- protected API/Mars.Web/read projections
- audit/idempotency/concurrency/targeted tests
- additive migration and TEST deployment

Explicit exclusions:
- Sales Invoice POST/REVERSE
- Finance Account Ledger/Inventory Valuation/Dispatch Cost Bridge/COGS
- Collection/settlement/open-item
- Invoice paid/open amount
- Warehouse Pick/Pack/Stage/Load
- TCMB/provider FX acquisition
- e-Invoice/e-Archive provider transmission
- Sales Return/RMA
- Sales Commercial Policy/price-list Settings administration
- automatic document numbering allocator
- production deployment
- Full Test Day

Implementation constraints:
- Quote has no RES/STOCK/ACCOUNT/CASH-BANK effect
- Order confirm is DOC only and never auto-reserves
- Inventory remains Reservation and physical quantity authority
- Dispatch POST is the Sales physical STOCK OUT point
- Dispatch POST composes Sales state + Inventory movement + linked Reservation consume atomically
- posted history uses reversal/compensation
- Invoice POST remains fail-closed until Finance authority exists
- no copied mutable reserved/shipped/invoiced totals become authority
- no client-supplied company/Warehouse authorization truth
- non-TRY final authoritative rounding/FX remains fail-closed until server-side policy exists

Planning:
- master 9 / 30 = 30.0%
- P2 8 / 8 = 100.0%
- separate repository inconsistency: docs/db/acceptance-criteria.md says 10 / 30 = 33.3%

Heavy tests remain Full Test Day only.
