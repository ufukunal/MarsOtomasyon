# Planning Backlog

## Immediate — P5 Parties next vertical slice definition
Status: SCOPE DEFINITION REQUIRED / NOT STARTED.

Predecessors:
- PARTY-IMP-001 — COMPLETED.
- PARTY-IMP-002 — COMPLETED.
- PARTY-IMP-003 — COMPLETED.
- PARTY-IMP-004 — COMPLETED.
- PARTY-IMP-005 — COMPLETED.
- PARTY-IMP-005 canonical report: `docs/plan/03-cariler/party-imp-005-implementation.md`
- Foundation Build run `35922536747` — SUCCESS.
- Foundation Test Deploy run `35922536768` — SUCCESS.

Repository-defined remaining Parties work includes:
- Party reactivate with current duplicate/legal identity validation;
- accepted soft duplicate candidate/review flow;
- Contact Person / Communication Point / Address;
- Party External Mapping;
- Party Merge;
- broader Tax Identity read/lifecycle/provider/non-TR support where later required.

No exact order or dedicated PARTY-IMP-006 ID is frozen.

Before implementation:
- reread frozen PLAN-003 and PLAN-010 contracts;
- compare dependency value and minimum authoritative entity/migration surface;
- preserve PARTY-IMP-001 through PARTY-IMP-005 company, permission, audit, idempotency, privacy and concurrency boundaries;
- do not invent fuzzy matching thresholds, provider/legal semantics or generic ERP fields;
- define exact DB/API/UI/permission/privacy/test impact;
- assign a dedicated implementation work-package ID only after scope is explicit.

Do not assume list order is implementation order.

## Quality — master planning sequence item 10
Target: docs/plan/08-kalite/
Status: PLANNING BACKLOG / NOT STARTED.

Quality remains the next section-8 planning item that can raise exact planning coverage from 9/30.
Operational execution belongs P6.

## Commerce/B2B/Architect/Marketplace
Target: docs/plan/15-e-ticaret-b2b-api/
Status: P7 / NOT IMMEDIATE.

## Full Test Day
Status: DEFERRED BY POLICY.

Party heavy risks include:
- concurrent Party Code create;
- concurrent role activation/lifecycle transition;
- concurrent deterministic Tax Identity collision;
- concurrent Party deactivation/state mutation;
- stale Party/role/tax identity state;
- cross-company IDOR;
- broad permission matrix;
- authenticated browser Party lifecycle E2E;
- consuming module eligibility races;
- provider reconciliation when introduced;
- high-volume duplicate/identity search;
- PII/security regression;
- snapshot persistence integration.

Exact section-8 planning progress:
- master: 9 / 30 = 30.0%
- P2: 8 / 8 = 100.0%
