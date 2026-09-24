# Planning Backlog

## Immediate — PRODUCT-IMP-001 Product Master Completion Tranche
Status: READY FOR IMPLEMENTATION.

Readiness:
- `docs/plan/04-urun-stok/p5-product-master-completion-readiness.md`

Single broad tranche:
- Product core create/list/detail/edit/lifecycle;
- UOM and Product-UOM;
- Variant;
- Barcode Mapping;
- Category/Product Category;
- generic Product External Mapping;
- protected API/Mars.Web;
- persistence/migration/audit/idempotency/concurrency/targeted tests.

Do not split these into additional PRODUCT-IMP work packages merely for implementation convenience.

Deferred:
- Inventory/Warehouse/Lot/Serial physical authority;
- stock/value/cost authority;
- Base UOM replacement;
- inventory-dependent STOCKABLE/tracking transitions;
- Variant EAV attributes;
- provider-specific sync/verification;
- production deployment;
- Full Test Day.

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
