# Active Tasks

## PARTY-IMP-005 — Deactivate Party
**Status:** READY — IMPLEMENTATION NOT STARTED

Canonical readiness:
- docs/plan/03-cariler/p5-fifth-slice-readiness.md

Predecessor:
- PARTY-IMP-004 — Manage Party Role Lifecycle — COMPLETED
- docs/plan/03-cariler/party-imp-004-implementation.md

Implement:
- existing Party ACTIVE → INACTIVE;
- party.deactivate;
- trusted-company Party lookup;
- expected-version stale-write protection;
- mandatory deactivation reason;
- audit + durable idempotency;
- protected deactivate endpoint;
- deactivation control on /parties/new;
- pending-model proof that no migration is required;
- targeted build/tests + TEST deploy/smoke.

Do not implement:
- Party reactivate;
- soft/fuzzy duplicate review;
- Contact/Communication/Address;
- Merge;
- Party External Mapping;
- Tax Identity follow-up;
- Sales/Purchasing eligibility;
- Finance behavior;
- production deployment;
- Full Test Day.

Exact planning progress:
- master: 9 / 30 = 30.0%
- P2: 8 / 8 = 100.0%
