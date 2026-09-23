# Planning Backlog

## Immediate — P5 Parties next vertical slice definition
Status: SCOPE DEFINITION REQUIRED / NOT STARTED.

Predecessors:
- PARTY-IMP-001 — Create Party Core Identity — COMPLETED.
- PARTY-IMP-002 — Activate Party Role — COMPLETED.
- PARTY-IMP-002 canonical report: `docs/plan/03-cariler/party-imp-002-implementation.md`
- Foundation Build run `35889499695` — SUCCESS.
- Foundation Test Deploy run `35889499708` — SUCCESS.

Repository-defined Parties follow-up requirements before full PLAN-003 parity:
- Tax Identity records and deterministic collision rules;
- accepted soft duplicate candidate/review flow;
- contacts/addresses;
- role lifecycle after activation;
- lifecycle/merge in later slices.

No exact order or dedicated next work-package ID is frozen.

Before implementation:
- reread frozen PLAN-003 and PLAN-010 contracts;
- compare dependency value and minimum authoritative entity/migration surface of the remaining candidates;
- select the smallest independently coherent Party use case;
- preserve PARTY-IMP-001/002 company, permission, audit and idempotency boundaries;
- define exact DB/API/UI/permission/test impact;
- assign a dedicated implementation work-package ID only after scope is explicit.

Do not assume list order is implementation order.

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
- concurrent Party Code create;
- concurrent role activation;
- future Tax Identity collision concurrency;
- stale Party/role state;
- cross-company IDOR;
- broad permission matrix;
- authenticated browser Party lifecycle/dual-role E2E;
- high-volume fuzzy duplicate search;
- snapshot persistence integration;
- Finance no-posting proof;
- security/privacy regression.

Exact section-8 planning progress:
- master: 9 / 30 = 30.0%
- P2: 8 / 8 = 100.0%
