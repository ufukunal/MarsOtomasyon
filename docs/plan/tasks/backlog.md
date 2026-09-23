# Planning Backlog

## Immediate — PARTY-IMP-003 Add Turkish Tax Identity
Status: READY / IMPLEMENTATION NOT STARTED.

Readiness:
- `docs/plan/03-cariler/p5-third-slice-readiness.md`

Scope:
- existing Party + trusted company;
- TR jurisdiction;
- VKN = exactly 10 ASCII digits;
- TCKN = exactly 11 ASCII digits;
- `party.tax_identity.manage`;
- deterministic active company/scheme/value collision;
- additive Tax Identity persistence/migration;
- protected API + /parties/new add flow;
- audit without raw VKN/TCKN;
- durable idempotency.

Explicitly deferred:
- checksum/provider/GİB enrollment verification;
- generic non-TR schemes;
- read/read_full/list/edit/deactivate;
- fuzzy duplicate review;
- contacts/addresses;
- lifecycle/merge;
- Finance/Sales/Purchasing integration.

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

Party heavy risks include:
- concurrent Party Code create;
- concurrent role activation;
- future Tax Identity collision concurrency;
- stale Party/role state;
- cross-company IDOR;
- broad permission matrix;
- authenticated browser Party lifecycle/dual-role E2E;
- high-volume fuzzy duplicate search;
- snapshot persistence integration;
- Finance no-posting proof;
- security/privacy regression.

Exact section-8 planning progress:
- master: 9 / 30 = 30.0%
- P2: 8 / 8 = 100.0%
