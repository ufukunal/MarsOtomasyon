# Active Tasks

## PARTY-IMP-004 — Manage Party Role Lifecycle
**Status:** READY — IMPLEMENTATION NOT STARTED

Canonical readiness:
- docs/plan/03-cariler/p5-fourth-slice-readiness.md

Predecessor:
- PARTY-IMP-003 — Add Turkish Tax Identity — COMPLETED
- docs/plan/03-cariler/party-imp-003-implementation.md

Implement:
- existing CUSTOMER/SUPPLIER role ACTIVE ↔ INACTIVE transitions;
- party.role.manage;
- trusted-company Party/role lookup;
- expected-version stale-write protection;
- mandatory deactivation reason;
- audit + durable idempotency;
- protected lifecycle endpoint;
- lifecycle control on /parties/new;
- pending-model proof that no migration is required;
- targeted build/tests + TEST deploy/smoke.

Do not implement:
- Party lifecycle;
- soft/fuzzy duplicate review;
- Contact/Communication/Address;
- Merge;
- Tax Identity follow-up;
- Sales/Purchasing eligibility;
- Finance behavior;
- production deployment;
- Full Test Day.

Exact planning progress:
- master: 9 / 30 = 30.0%
- P2: 8 / 8 = 100.0%
