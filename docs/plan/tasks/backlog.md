# Planning Backlog

## Immediate — PARTY-IMP-004 Manage Party Role Lifecycle
Status: READY / IMPLEMENTATION NOT STARTED.

Readiness:
- docs/plan/03-cariler/p5-fourth-slice-readiness.md

Scope:
- existing Party Role ACTIVE ↔ INACTIVE;
- CUSTOMER/SUPPLIER only;
- party.role.manage;
- expected-version optimistic concurrency;
- deactivation reason required;
- audit + durable idempotency;
- protected role-state endpoint;
- /parties/new lifecycle UX;
- no EF model change/migration expected.

Explicitly deferred:
- fuzzy duplicate review;
- Contact/Communication/Address;
- Party lifecycle/merge;
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
- concurrent role activation;
- concurrent deterministic Tax Identity collision;
- stale Party/role/tax identity state;
- cross-company IDOR;
- broad permission matrix;
- authenticated browser Party lifecycle E2E;
- provider reconciliation when introduced;
- high-volume duplicate/identity search;
- PII logging/export/security regression;
- snapshot persistence integration.

Exact section-8 planning progress:
- master: 9 / 30 = 30.0%
- P2: 8 / 8 = 100.0%
