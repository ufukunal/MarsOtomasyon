# Active Tasks

## P5 — Parties next vertical slice definition
**Status:** SCOPE DEFINITION REQUIRED — IMPLEMENTATION NOT STARTED

Predecessors:
- PARTY-IMP-001 — Create Party Core Identity — COMPLETED
- PARTY-IMP-002 — Activate Party Role — COMPLETED
- PARTY-IMP-003 — Add Turkish Tax Identity — COMPLETED
- PARTY-IMP-004 — Manage Party Role Lifecycle — COMPLETED
- PARTY-IMP-005 — Deactivate Party — COMPLETED

PARTY-IMP-005 evidence:
- canonical report: `docs/plan/03-cariler/party-imp-005-implementation.md`
- Foundation Build run `35922536747` — SUCCESS
- Foundation Test Deploy run `35922536768` — SUCCESS
- frontend tests 16 / 16 PASS
- Foundation targeted tests 55 / 55 PASS
- no EF model change; migration count remains 5
- Party deactivate endpoint unauthenticated 401
- live/ready 200 / 200
- runner-to-TEST smoke PASS

Repository-defined remaining candidates:
- Party reactivate with duplicate/legal identity validation gate;
- accepted soft duplicate candidate/review;
- Contact Person / Communication Point / Address;
- Party External Mapping;
- Party Merge;
- broader Tax Identity follow-up where later required.

Current gate:
- no dedicated next implementation ID exists;
- repository does not freeze the next order;
- choose the smallest coherent next Party vertical slice from frozen PLAN-003 + PLAN-010;
- map DB/API/UI/permission/privacy/concurrency/migration/test effects before mutation;
- assign a dedicated work-package ID only after exact scope is explicit.

Exact planning progress:
- master: 9 / 30 = 30.0%
- P2: 8 / 8 = 100.0%

Heavy tests remain Full Test Day only.
