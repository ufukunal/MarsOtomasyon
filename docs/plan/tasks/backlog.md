# Planning Backlog

## Immediate — PARTY-IMP-006 Party Master Completion Tranche
Status: READY FOR IMPLEMENTATION.

Owner direction:
- broaden Party implementation scope;
- do not create a separate implementation package for each small capability.

Predecessor:
- PARTY-IMP-005 — COMPLETED.
- canonical report: `docs/plan/03-cariler/party-imp-005-implementation.md`
- Build `35922536747` — SUCCESS.
- Test Deploy `35922536768` — SUCCESS.

Readiness:
- `docs/plan/03-cariler/p5-party-master-completion-readiness.md`

Single broad tranche:
- Party directory/detail/read;
- Party legal/display identity edit;
- Contact Person / Communication Point;
- Address lifecycle/default-by-purpose;
- current TR VKN/TCKN read/masking/lifecycle;
- Party External Mapping;
- explicit Party Merge + lineage;
- required permission/API/UI/persistence/migration/audit/idempotency/concurrency/targeted-test work.

Deferred outside PARTY-IMP-006:
- fuzzy duplicate candidate generation;
- Party Reactivation;
- provider/GIB and non-TR tax behavior;
- Communications consent/preferences;
- Sales/Purchasing/Finance implementation;
- production deployment;
- Full Test Day.

Do not split included scope into additional PARTY-IMP work packages merely for implementation convenience. Internal sequencing is allowed inside PARTY-IMP-006.

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
