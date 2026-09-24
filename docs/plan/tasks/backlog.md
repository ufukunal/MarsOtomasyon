# Planning Backlog

## Immediate — P5 Inventory broad implementation tranche definition
Status: SCOPE DEFINITION REQUIRED / NOT STARTED.

Predecessor:
- PRODUCT-IMP-001 — COMPLETED.
- canonical report: docs/plan/04-urun-stok/product-imp-001-implementation.md
- tested commit: ac92911a8f5fcda070622b084216a43a70ad7d77
- Foundation Build 35981641268 — SUCCESS.
- Foundation Test Deploy 35981641137 — SUCCESS.
- migration count 7.

Direction:
- master P5 order moves from Products to Inventory
- use frozen PLAN-004 Inventory contracts + PLAN-010
- define one broad Inventory implementation tranche, not many small slices

Before implementation:
- inspect current Inventory source
- freeze Inventory Ledger / Reservation / status / lot-serial boundary from accepted sources
- distinguish Warehouse operational ownership
- preserve Finance/Costing valuation ownership
- define DB/API/UI/permission/idempotency/concurrency/test effects
- assign Inventory implementation ID only after broad scope is explicit

Do not invent negative-stock exceptions, lot/serial bypasses, mutable stock totals, Warehouse workflow ownership or Finance valuation authority.

## Quality — master planning sequence item 10
Target: docs/plan/08-kalite/
Status: PLANNING BACKLOG / NOT STARTED.

Quality remains the next section-8 planning item that can raise exact planning coverage from 9/30.
Operational execution belongs P6.

## Commerce/B2B/Architect/Marketplace
Target: docs/plan/15-e-ticaret-b2b-api/
Status: P7 / NOT IMMEDIATE.

## Full Test Day
Status: DEFERRED BY POLICY.

Party heavy risks include:
- concurrent Party Code create;
- concurrent role activation/lifecycle transition;
- concurrent deterministic Tax Identity collision;
- concurrent Party deactivation/state mutation;
- stale Party/role/tax identity state;
- cross-company IDOR;
- broad permission matrix;
- authenticated browser Party lifecycle E2E;
- consuming module eligibility races;
- provider reconciliation when introduced;
- high-volume duplicate/identity search;
- PII/security regression;
- snapshot persistence integration.

Exact section-8 planning progress:
- master: 9 / 30 = 30.0%
- P2: 8 / 8 = 100.0%
