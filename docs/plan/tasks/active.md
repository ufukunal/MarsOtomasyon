# Active Tasks

## P5 — Products broad implementation tranche definition
**Status:** SCOPE DEFINITION REQUIRED / NOT STARTED

Predecessor:
- PARTY-IMP-006 — Party Master Completion Tranche — COMPLETED
- canonical report: `docs/plan/03-cariler/party-imp-006-implementation.md`
- tested commit: `55b7e78bc2eabcf986e8318f60296f0f3f9cd223`
- Foundation Build `35934312246` — SUCCESS
- Foundation Test Deploy `35934312470` — SUCCESS
- frontend 18 / 18 PASS
- Foundation 61 / 61 PASS
- committed migration count 6
- TEST /parties = 200
- live/ready = 200 / 200
- smoke PASS

Repository direction:
- master P5 sequence places Products after Parties;
- use frozen PLAN-004 and PLAN-010;
- define one broad coherent Product implementation tranche;
- do not split every Product capability into separate micro-packages merely for implementation convenience.

Before implementation:
- inspect current Product source state;
- freeze included Product master capability set;
- freeze Product vs Inventory/Warehouse/Finance boundaries;
- map entities/constraints/migration/API/UI/permissions/concurrency/tests;
- assign a Product implementation package ID only after scope is explicit.

Forbidden assumptions:
- arbitrary generic attributes/EAV;
- provider-specific product sync behavior;
- Product.current_stock as authority;
- Product current inventory valuation/cost authority;
- Warehouse operational workflows silently absorbed into Product master.

Planning metrics:
- active state master: 9 / 30 = 30.0%
- P2: 8 / 8 = 100.0%
- separate repo inconsistency: docs/db/acceptance-criteria.md says 10 / 30 = 33.3%

Heavy tests remain Full Test Day only.
