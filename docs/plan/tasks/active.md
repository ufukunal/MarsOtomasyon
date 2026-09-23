# Active Tasks

## PARTY-IMP-003 — Add Turkish Tax Identity
**Status:** READY — IMPLEMENTATION NOT STARTED

Canonical readiness:
- `docs/plan/03-cariler/p5-third-slice-readiness.md`

Predecessor:
- PARTY-IMP-002 — Activate Party Role — COMPLETED
- `docs/plan/03-cariler/party-imp-002-implementation.md`

Implement:
- one ACTIVE TR VKN/TCKN Tax Identity child;
- `party.tax_identity.manage`;
- trusted-company Party lookup;
- deterministic active company + jurisdiction + scheme + value conflict;
- additive EF migration;
- POST `/api/v1/parties/{partyPublicId}/tax-identities`;
- Tax Identity add section on `/parties/new`;
- audit without raw identity value;
- durable idempotency;
- targeted verification + TEST deploy/smoke.

Do not implement:
- checksum/provider/GİB validation;
- generic non-TR tax schemes;
- tax identity read/full/list/edit/deactivate;
- soft duplicate review;
- contacts/addresses;
- Party/role lifecycle or merge;
- Finance/Sales/Purchasing behavior;
- production deployment;
- Full Test Day.

Exact planning progress:
- master: 9 / 30 = 30.0%
- P2: 8 / 8 = 100.0%
