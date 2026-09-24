# Active Tasks

## Finance / Treasury Broad Implementation Tranche Definition

**Status:** SCOPE DEFINITION REQUIRED / NOT STARTED

Implementation work-package ID:
- UNASSIGNED until the exact broad scope is frozen.

Planning source:
- frozen PLAN-007
- docs/plan/09-finans-kasa-banka/

Completed predecessor:
- WAREHOUSE-IMP-001 — COMPLETED / RUNTIME VERIFIED
- canonical report: docs/plan/06-ambar-depo/warehouse-imp-001-implementation.md
- tested commit: 81efd9c347118db24d8945f4d49e63bf99baaf70
- Foundation Build 36068722249 — SUCCESS
- Foundation Test Deploy 36068722239 — SUCCESS
- migration baseline 13

Required next action:
- reconcile existing Sales / Purchasing / Inventory / Warehouse implementation against PLAN-007;
- freeze one broad coherent Finance / Treasury implementation tranche;
- define Account Ledger, Cash/Bank, settlement, posting-period, valuation, Dispatch cost bridge and currently fail-closed financial-posting boundaries;
- assign the Finance implementation package ID only after exact scope freeze.

Do not:
- repeat portfolio/module planning;
- split Finance into micro implementation packages during scope definition;
- begin implementation mutation before the broad boundary is frozen;
- run Full Test Day;
- deploy production.

Planning:
- portfolio 30 / 30 = 100.0%
- detailed master planning 30 / 30 = 100.0%
