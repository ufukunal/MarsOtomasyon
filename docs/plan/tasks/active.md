# Active Tasks

## PARTY-IMP-002 — Activate Party Role
**Status:** READY — IMPLEMENTATION NOT STARTED

Canonical readiness:
- `docs/plan/03-cariler/p5-second-slice-readiness.md`

Predecessor:
- PARTY-IMP-001 — Create Party Core Identity — COMPLETED
- `docs/plan/03-cariler/party-imp-001-implementation.md`

Implement:
- Party Role persistence for CUSTOMER/SUPPLIER activation;
- `party.role.manage` using existing Mars PostgreSQL permission authority;
- trusted-company Party lookup;
- additive EF migration;
- POST `/api/v1/parties/{partyPublicId}/roles`;
- post-create CUSTOMER/SUPPLIER activation on `/parties/new`;
- audit + durable idempotency;
- targeted build/test/model/migration/API/Web verification;
- TEST deploy/smoke when deployable.

Do not implement:
- role deactivate/reactivate;
- role-specific defaults/codes;
- Tax Identity/contact/address/duplicate-review/merge;
- Finance/Sales/Purchasing behavior;
- production deployment;
- Full Test Day.

Exact planning progress:
- master: 9 / 30 = 30.0%
- P2: 8 / 8 = 100.0%
