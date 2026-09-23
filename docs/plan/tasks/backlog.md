# Planning Backlog

## Immediate — P5 Parties next vertical slice definition
Status: SCOPE DEFINITION REQUIRED / NOT STARTED.

Predecessor:
- PARTY-IMP-001 — Create Party Core Identity — COMPLETED.
- canonical report: `docs/plan/03-cariler/party-imp-001-implementation.md`
- Foundation Build run: `35885316247` — SUCCESS
- Foundation Test Deploy run: `35885323206` — SUCCESS

Repository-defined Parties follow-up requirements before full PLAN-003 Create Party parity:
- accepted soft duplicate candidate/review flow;
- Tax Identity records and deterministic collision rules;
- Party Role activation;
- contacts/addresses;
- lifecycle/merge in later slices.

No exact order or dedicated next work-package ID is frozen.

Before implementation:
- reread frozen PLAN-003 and PLAN-010 Party contracts;
- compare dependency value and minimum entity/migration surface of the follow-up candidates;
- select the smallest coherent next Party vertical slice;
- preserve PARTY-IMP-001 company/permission/audit/idempotency boundaries;
- define exact DB/API/UI/permission/test impact;
- assign a dedicated implementation work-package ID only after scope is explicit.

Do not assume the first bullet above is automatically the next package.

## Quality — master planning sequence item 10
Target: docs/plan/08-kalite/
Status: PLANNING BACKLOG / NOT STARTED.

Quality remains the next section-8 item that can raise exact planning completion from 9/30.
Operational execution belongs P6.

## Commerce/B2B/Architect/Marketplace
Target: docs/plan/15-e-ticaret-b2b-api/
Status: P7 / NOT IMMEDIATE.

## Full Test Day
Status: DEFERRED BY POLICY.

Party heavy risks include:
- concurrent Party Code allocation/create conflict;
- future deterministic Tax Identity collision concurrency;
- stale Party edits;
- cross-company IDOR;
- broad permission matrix;
- authenticated browser Party lifecycle E2E;
- high-volume fuzzy duplicate search;
- snapshot persistence integration;
- security/privacy regression.

Exact section-8 planning progress:
- master: 9 / 30 = 30.0%
- P2: 8 / 8 = 100.0%
