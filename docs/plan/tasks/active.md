# Active Tasks

## P5 — Parties next vertical slice definition
**Status:** SCOPE DEFINITION REQUIRED — IMPLEMENTATION NOT STARTED

Predecessors:
- PARTY-IMP-001 — Create Party Core Identity — COMPLETED
- PARTY-IMP-002 — Activate Party Role — COMPLETED
- PARTY-IMP-003 — Add Turkish Tax Identity — COMPLETED

PARTY-IMP-003 evidence:
- canonical report: `docs/plan/03-cariler/party-imp-003-implementation.md`
- Foundation Build run `35894175633` — SUCCESS
- Foundation Test Deploy run `35894175558` — SUCCESS
- frontend tests 14 / 14 PASS
- Foundation targeted tests 45 / 45 PASS
- TEST Tax Identity migration applied; migration count 5
- Tax Identity endpoint unauthenticated 401
- live/ready 200 / 200
- runner-to-TEST smoke PASS

Repository-defined remaining candidates:
- accepted soft duplicate candidate/review flow;
- contacts/addresses;
- role lifecycle after activation;
- Party lifecycle/merge;
- broader Tax Identity lifecycle/provider/non-TR support where later required.

Current gate:
- no dedicated next implementation ID exists;
- repository does not freeze an order among the remaining capabilities;
- choose the smallest coherent next Party vertical slice from frozen PLAN-003 + PLAN-010;
- map DB/API/UI/permission/privacy/migration/test effects before mutation;
- assign a dedicated work-package ID only after exact scope is explicit.

Exact planning progress:
- master: 9 / 30 = 30.0%
- P2: 8 / 8 = 100.0%

Heavy tests remain Full Test Day only.
