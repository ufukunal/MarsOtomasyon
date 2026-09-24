# Active Tasks

## WAREHOUSE-IMP-001 — Warehouse Execution & Inventory Control Authority Tranche

**Status:** READY FOR IMPLEMENTATION / NOT STARTED

Canonical readiness:
- docs/plan/06-ambar-depo/p5-warehouse-execution-readiness.md

Predecessor:
- PURCHASING-IMP-001 — COMPLETED
- canonical report: docs/plan/07-satinalma/purchasing-imp-001-implementation.md
- tested commit: 50ec242e5471743bbdc9ad43bc69626166b1679f
- Foundation Build 36054248023 — SUCCESS
- Foundation Test Deploy 36054247926 — SUCCESS
- migration baseline 11

Broad included authority:
- Warehouse permission catalog and Warehouse access scope
- receiving/quarantine read queue
- disposition and release through Inventory physical authority
- put-away and manual replenishment
- pick work + FEFO/FIFO recommendation + controlled override
- Sales-owned pre-POST Dispatch source binding
- package/packing/staging/loading work
- transfer ISSUE -> TRANSIT -> partial/full RECEIVE -> reconcile/reverse
- Stock Count snapshot/intervening/review/approval
- negative COUNT_ADJUSTMENT posting
- positive COUNT_ADJUSTMENT fail-closed until Finance valuation authority
- damage/scrap physical workflow
- Warehouse/Location deactivation operational blockers
- offline/client-operation idempotency/conflict journal
- protected API/Mars.Web/read projections
- additive migration and TEST deployment

Hard boundaries:
- no duplicate Sales Dispatch STOCK OUT
- no duplicate Purchasing Goods Receipt STOCK IN
- no Finance valuation/account posting
- no Quality inspection authority
- no negative-stock override
- no production
- no Full Test Day

Planning:
- portfolio 30 / 30 = 100.0%
- detailed master planning 30 / 30 = 100.0%
