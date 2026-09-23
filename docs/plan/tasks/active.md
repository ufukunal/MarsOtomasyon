# Active Tasks

## P5 — Parties next vertical slice definition
**Status:** SCOPE DEFINITION REQUIRED — IMPLEMENTATION NOT STARTED

Predecessors:
- PARTY-IMP-001 — Create Party Core Identity — COMPLETED
- PARTY-IMP-002 — Activate Party Role — COMPLETED
- PARTY-IMP-003 — Add Turkish Tax Identity — COMPLETED
- PARTY-IMP-004 — Manage Party Role Lifecycle — COMPLETED

PARTY-IMP-004 evidence:
- canonical report: `docs/plan/03-cariler/party-imp-004-implementation.md`
- Foundation Build run `35910331829` — SUCCESS
- Foundation Test Deploy run `35910331820` — SUCCESS
- frontend tests 15 / 15 PASS
- Foundation targeted tests 50 / 50 PASS
- no EF model change; migration count remains 5
- Party Role lifecycle endpoint unauthenticated 401
- live/ready 200 / 200
- runner-to-TEST smoke PASS

Repository-defined remaining candidates:
- accepted soft duplicate candidate/review flow;
- Contact Person / Communication Point / Address;
- Party deactivate/reactivate;
- Party Merge;
- Party External Mapping;
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
