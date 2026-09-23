# Active Tasks

## PARTY-IMP-001 — Create Party Core Identity
**Status:** READY — IMPLEMENTATION NOT STARTED

Canonical readiness:
- `docs/plan/03-cariler/p5-first-slice-readiness.md`

Authorization decision:
- `docs/plan/decisions/ADR-0005-mars-erp-permission-authority.md`

Implement:
- Mars-owned PostgreSQL Permission Grant evaluator for `party.create`;
- Party core identity aggregate/persistence;
- additive EF migration;
- protected `POST /api/v1/parties`;
- `/parties/new` Mars.Web form;
- audit + durable idempotency;
- targeted build/tests/migration/API/Web verification;
- TEST deploy/smoke when ready.

Locked:
- Party Code is required caller input; no allocator/format invented.
- CompanyId comes only from trusted execution context and has no Company FK in this slice.
- soft/fuzzy duplicate warning is explicitly deferred.
- Party Role/Tax/Contact/Address/Mapping/Merge are deferred.
- no Finance/Stock/Account/Cash authority enters Parties.

Exact planning progress:
- master: 9 / 30 = 30.0%
- P2: 8 / 8 = 100.0%

Heavy suites remain Full Test Day only.
