# Active Tasks

## P5 — Parties next vertical slice definition
**Status:** SCOPE DEFINITION REQUIRED — IMPLEMENTATION NOT STARTED

Predecessors:
- PARTY-IMP-001 — Create Party Core Identity — COMPLETED
- PARTY-IMP-002 — Activate Party Role — COMPLETED

PARTY-IMP-002 evidence:
- canonical report: `docs/plan/03-cariler/party-imp-002-implementation.md`
- Foundation Build run `35889499695` — SUCCESS
- Foundation Test Deploy run `35889499708` — SUCCESS
- frontend tests 13 / 13 PASS
- Foundation targeted tests 40 / 40 PASS
- TEST Party Role migration applied; migration count 4
- role endpoint unauthenticated 401
- live/ready 200 / 200
- runner-to-TEST smoke PASS

Repository-defined follow-up requirements:
- Tax Identity records and deterministic collision rules;
- accepted soft duplicate candidate/review flow;
- contacts/addresses;
- role lifecycle after activation;
- lifecycle/merge in later slices.

Current gate:
- no dedicated next implementation ID exists;
- repository does not freeze an order among these remaining capabilities;
- choose the smallest coherent next Party vertical slice from frozen PLAN-003 + PLAN-010;
- map DB/API/UI/permission/migration/test effects before mutation;
- assign a dedicated work-package ID only after exact scope is explicit.

Exact planning progress:
- master: 9 / 30 = 30.0%
- P2: 8 / 8 = 100.0%

Heavy tests remain Full Test Day only.
