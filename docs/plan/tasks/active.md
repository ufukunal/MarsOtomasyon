# Active Tasks

## Warehouse broad implementation tranche definition

**Status:** SCOPE DEFINITION REQUIRED

Formal WAREHOUSE-IMP work package ID is intentionally not assigned yet.

Predecessor:
- PURCHASING-IMP-001 — COMPLETED
- canonical report: docs/plan/07-satinalma/purchasing-imp-001-implementation.md
- tested commit: 50ec242e5471743bbdc9ad43bc69626166b1679f
- Foundation Build 36054248023 — SUCCESS
- Foundation Test Deploy 36054247926 — SUCCESS
- deployed migration count 11

Canonical planning basis:
- docs/plan/06-ambar-depo/plan.md
- docs/plan/06-ambar-depo/workflows.md
- docs/plan/06-ambar-depo/data-contract.md
- docs/plan/06-ambar-depo/permissions.md
- docs/plan/06-ambar-depo/acceptance-criteria.md

Required next work:
- reconcile implemented Inventory, Sales and Purchasing authority boundaries;
- define one broad coherent Warehouse implementation tranche;
- freeze exact includes/excludes, persistence, API/Web and normal-test evidence;
- preserve Sales Dispatch as the Sales STOCK OUT point;
- preserve Purchasing Goods Receipt as the Purchasing STOCK IN point;
- only after scope freeze assign a WAREHOUSE-IMP ID and readiness document.

Do not repeat portfolio/module planning.

Planning:
- portfolio 30 / 30 = 100.0%
- detailed master planning 30 / 30 = 100.0%

Heavy tests remain Full Test Day only.
