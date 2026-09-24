# Planning Backlog

## Immediate — P5 Products broad implementation tranche definition
Status: SCOPE DEFINITION REQUIRED / NOT STARTED.

Predecessor:
- PARTY-IMP-006 — COMPLETED.
- canonical report: `docs/plan/03-cariler/party-imp-006-implementation.md`
- tested commit: `55b7e78bc2eabcf986e8318f60296f0f3f9cd223`
- Foundation Build `35934312246` — SUCCESS.
- Foundation Test Deploy `35934312470` — SUCCESS.
- migration count 6.

Direction:
- master P5 order moves from Parties to Products;
- use frozen PLAN-004 + PLAN-010;
- define one broad Product implementation tranche, not many small slices.

Before implementation:
- inspect current Product implementation state;
- freeze Product master scope and explicit exclusions;
- preserve Product vs Inventory/Warehouse/Finance authority;
- define exact DB/API/UI/permission/concurrency/test effect;
- assign a Product implementation ID only after broad scope is explicit.

Do not invent:
- generic EAV/attribute model;
- provider-specific product sync;
- authoritative mutable stock or inventory value on Product;
- Warehouse workflows not owned by Product.

Remaining Party deferrals do not block moving to Products:
- fuzzy duplicate candidate generation;
- Party Reactivation;
- provider/GİB/non-TR identity;
- Communications consent/preferences;
- consuming-module eligibility / Finance integration.

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
