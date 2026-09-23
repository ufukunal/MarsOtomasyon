# Planning Backlog

## Immediate — PARTY-IMP-001 Create Party Core Identity
Status: READY / NOT STARTED.

Readiness:
- `docs/plan/03-cariler/p5-first-slice-readiness.md`

Authorization ADR:
- `docs/plan/decisions/ADR-0005-mars-erp-permission-authority.md`

Scope:
- implement Foundation Permission Grant authority/evaluator for `party.create`;
- implement Party core identity persistence;
- additive migration;
- POST `/api/v1/parties`;
- Mars.Web `/parties/new`;
- audit/idempotency;
- targeted verification + TEST smoke.

Explicitly deferred:
- Party roles;
- tax identities;
- contacts;
- addresses;
- mappings;
- merge;
- soft/fuzzy duplicate warning;
- Settings/Numbering allocator;
- permission administration UI/roles.

## Parties follow-up required before full PLAN-003 Create Party parity
- accepted soft duplicate candidate/review flow;
- tax identity records and deterministic collision rules;
- role activation;
- contacts/addresses;
- lifecycle/merge in later slices.

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

Exact section-8 planning progress:
- master: 9 / 30 = 30.0%
- P2: 8 / 8 = 100.0%
