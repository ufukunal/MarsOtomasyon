# Active Tasks

## PRODUCT-IMP-001 — Product Master Completion Tranche
**Status:** READY FOR IMPLEMENTATION

Readiness:
- `docs/plan/04-urun-stok/p5-product-master-completion-readiness.md`

Owner direction:
- one broad coherent Product package;
- no micro-package split for Product/Variant/UOM/Barcode/Category/External Mapping.

Includes:
- Product core create/list/detail/edit/lifecycle;
- UOM + Product-UOM;
- Variant;
- Barcode;
- Category/Product Category;
- Product External Mapping;
- protected API/Mars.Web;
- normalized persistence/additive migration;
- audit/idempotency/concurrency/targeted tests.

Deferred:
- Inventory/Warehouse physical authority;
- Lot/Serial records and movements;
- stock/value/cost authority;
- Base UOM replacement and inventory-dependent STOCKABLE/tracking transitions;
- generic attribute/EAV;
- provider sync/verification;
- production deployment;
- Full Test Day.

Predecessor:
- PARTY-IMP-006 — COMPLETED
- tested SHA `55b7e78bc2eabcf986e8318f60296f0f3f9cd223`
- Build `35934312246` — SUCCESS
- Test Deploy `35934312470` — SUCCESS
- migration baseline 6

Planning metrics:
- active master: 9 / 30 = 30.0%
- P2: 8 / 8 = 100.0%
- known separate metric inconsistency: docs/db/acceptance-criteria.md says 10 / 30 = 33.3%

Heavy tests remain Full Test Day only.
