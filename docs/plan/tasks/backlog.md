# Planning Backlog

## Immediate — PARTY-IMP-005 Deactivate Party
Status: READY / IMPLEMENTATION NOT STARTED.

Readiness:
- docs/plan/03-cariler/p5-fifth-slice-readiness.md

Scope:
- existing Party ACTIVE → INACTIVE;
- party.deactivate;
- trusted-company Party lookup;
- expected-version optimistic concurrency;
- mandatory reason;
- audit + durable idempotency;
- protected deactivate endpoint;
- /parties/new deactivation UX;
- no EF model change/migration expected.

Explicitly deferred:
- Party reactivate;
- fuzzy duplicate review;
- Contact/Communication/Address;
- Merge;
- External Mapping;
- Tax Identity follow-up;
- Sales/Purchasing eligibility;
- Finance integration.

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
