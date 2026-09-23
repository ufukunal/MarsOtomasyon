# P5 Parties — Fifth Vertical Slice Readiness

Status: IMPLEMENTED / COMPLETED
Date: 2026-09-24
Phase: P5 — Core application implementation
Module: Parties
Implementation work package: PARTY-IMP-005
Title: Deactivate Party

## Objective

Freeze the smallest coherent Parties vertical slice after PARTY-IMP-004.

Selected slice:

**Deactivate Party**

The slice changes one existing Party from ACTIVE to INACTIVE with a mandatory reason while preserving company scope, roles, Tax Identities, history and Finance separation.

It does not implement Party reactivation, soft duplicate review, contacts/addresses, merge, external mappings, or consuming Sales/Purchasing eligibility enforcement.

## Candidate comparison

### Party deactivation — SELECTED

Why:
- PLAN-003 freezes ACTIVE → INACTIVE as an explicit Party action;
- party.deactivate is a distinct accepted permission;
- mandatory reason and audit are already frozen;
- existing Party persistence already contains state + optimistic version;
- no new authoritative entity or migration is required;
- deactivation has no Finance/stock/account/cash/cost posting;
- no fuzzy matching/provider/legal rule is needed to deactivate;
- history remains readable and document snapshots are unchanged;
- independently testable through API + existing /parties/new flow.

Reactivation is intentionally excluded because:
- it has a separate permission (party.reactivate);
- PLAN-003 requires current duplicate/legal identity validation to rerun;
- soft duplicate candidate/review is still not implementation-ready;
- combining both directions would either invent missing duplicate behavior or weaken the frozen reactivation gate.

### Soft duplicate candidate/review — deferred

Not implementation-ready:
- no accepted normalization/similarity algorithm/score/threshold;
- fuzzy match is warning/review only;
- no auto merge;
- deterministic collisions remain non-overridable.

### Contact / Communication / Address — deferred

Why not next:
- Contact Person + Communication Point introduce multiple authoritative child entities;
- Address introduces structured address parts, purpose/default semantics and downstream snapshot use;
- wider migration/API/UI surface than a state transition already represented by Party.

### Party External Mapping — deferred

Why not next:
- one child entity, but source/provider/system + optional scope/account normalization and exact physical identity semantics are less frozen;
- provider/account/system uniqueness is required, but exact normalization strategy is not yet explicit enough for a dependency-minimal physical slice without additional decisions.

### Party Merge — deferred

Why not next:
- requires survivor/source lineage;
- conflict review across roles/tax/contact/address/external mappings;
- Contact/Address/External Mapping are not yet implemented;
- high-risk permission/reason/history flow.

### Tax Identity follow-up — deferred

Why not next:
- read/read_full requires sensitive disclosure/masking contract;
- edit/deactivate/provider/non-TR behavior needs separately frozen lifecycle/legal rules.

## Frozen invariants

- Party remains a company-scoped authoritative identity.
- Existing state values remain ACTIVE / INACTIVE / MERGED.
- This slice accepts ACTIVE → INACTIVE only.
- INACTIVE is not hard delete.
- MERGED is terminal for this command and cannot be deactivated.
- Deactivation requires party.deactivate.
- Deactivation requires a non-empty reason.
- CompanyId comes only from trusted IExecutionContext.
- Request cannot choose CompanyId.
- Deactivation does not mutate CUSTOMER/SUPPLIER roles.
- Deactivation does not mutate Tax Identities.
- Deactivation does not rewrite historical document snapshots.
- Existing transactions are not cancelled/reversed.
- Party becomes unavailable for new counterparty selection by default, but consuming-module eligibility enforcement is not implemented here.
- No Finance/Account/Cash/Bank/Stock/Reservation/Cost effect.
- No automatic balance netting.
- ADR-0005 remains ERP permission authority.

## Existing physical DB contract

No new table, column or index is required.

Existing authoritative table:
- parties.parties.

Existing fields used:
- public_id;
- company_id;
- state;
- version.

Existing constraints remain:
- state in Active/Inactive/Merged;
- version > 0;
- optimistic concurrency token on version.

Concurrency:
- command requires positive expected Party version;
- persistence resolves Party by trusted CompanyId + public UUID;
- expected-version mismatch → stale conflict;
- EF optimistic concurrency protects a race after read;
- accepted transition increments version by 1;
- already INACTIVE → conflict;
- MERGED → conflict.

Migration:
- NOT REQUIRED if EF model remains unchanged;
- pending-model verification must prove no migration is required;
- no empty/no-op migration may be created.

## Domain / Application contract

Command:
- DeactivateParty.

Input:
- Party public UUID;
- expected positive version;
- mandatory reason;
- idempotency key.

Trusted context:
- ActorId;
- CompanyId;
- optional BranchId;
- CorrelationId.

Application checks:
- party.deactivate;
- non-empty Party public UUID;
- expected version > 0;
- non-empty bounded reason;
- durable bounded Idempotency-Key.

Persistence outcomes:
- Deactivated;
- PartyNotFound;
- DuplicateOperation;
- StaleVersion;
- AlreadyInactive;
- MergedStateConflict.

Result:
- Party public UUID;
- state INACTIVE;
- incremented version;
- correlation id.

## Transaction / audit / idempotency / outbox

One PostgreSQL transaction:
1. resolve Party by trusted CompanyId + Party public UUID;
2. validate expected version;
3. validate current state;
4. update Party state to INACTIVE and increment version;
5. insert Foundation idempotency operation;
6. append audit;
7. save;
8. complete idempotency;
9. commit.

Audit:
- action PartyDeactivated;
- actor/company/correlation;
- Party public UUID;
- mandatory reason.

Idempotency:
- required;
- company-scoped Party deactivation operation;
- duplicate operation key → conflict.

Outbox:
- NOT REQUIRED in PARTY-IMP-005;
- PartyDeactivated remains a candidate event;
- no accepted consumer currently requires publication in this slice.

## API contract

Endpoint:
- POST /api/v1/parties/{partyPublicId}/deactivate

Header:
- Idempotency-Key required.

Body:
- version: positive expected Party version;
- reason: required.

No CompanyId field.

Expected mapping:
- unauthenticated → 401;
- authenticated without party.deactivate → 403;
- invalid Party/version/reason/idempotency → 400;
- Party absent in trusted company → 404;
- duplicate operation → 409;
- stale version → 409;
- already INACTIVE → 409;
- MERGED → 409;
- success → 200.

## UI contract

Reuse existing /parties/new journey.

After successful Party creation:
- retain Party public UUID and version in page state;
- show Party status section;
- allow explicit deactivate action;
- require deactivation reason;
- send current expected Party version;
- use a fresh idempotency key;
- request contains no CompanyId;
- success updates visible Party state to INACTIVE and version;
- deactivation control becomes disabled after success;
- role/Tax Identity history already created on the Party is not erased;
- 401/403/404/409/validation errors remain explicit.

This page does not claim to be a general existing-Party detail/editor because Party read/detail is not yet implemented.

Reactivation control is not added in PARTY-IMP-005.

## Security / privacy

- party.deactivate enforced by API policy and handler.
- Authentication alone is insufficient.
- Company scope comes from trusted execution context.
- route Party UUID does not bypass company filtering.
- UI state/visibility is not authorization.
- no Tax Identity value is involved.
- no Finance permission or authority is gained.

## Targeted verification

Normal development:
- .NET Release build;
- missing party.deactivate denied before persistence;
- empty Party UUID;
- invalid expected version;
- missing/blank deactivation reason;
- trusted CompanyId scope;
- Party-not-found;
- duplicate operation;
- stale version;
- already inactive conflict;
- merged state conflict;
- successful ACTIVE → INACTIVE increments version;
- audit action/reason;
- write contract does not mutate roles/Tax Identities;
- EF pending-model check proves no migration;
- frontend typecheck/tests/build;
- UI deactivation request contains expected version + reason;
- no client CompanyId;
- API/OpenAPI smoke;
- TEST deploy/readiness/smoke.

Remote smoke may prove:
- /parties/new 200;
- unauthenticated Party deactivate POST 401;
- health/readiness 200;
- OpenAPI route present;
- migration count remains 5.

A real authenticated Party deactivation on TEST is not required for normal smoke unless an existing safe principal/grant path exists; no auth bypass may be invented.

## Full Test Day pending

- concurrent deactivate/deactivate;
- deactivate racing Party edit;
- deactivate racing role/tax mutation;
- deactivate racing Sales/Purchasing document creation;
- cross-company IDOR;
- broad permission matrix;
- authenticated browser create → deactivate;
- existing CUSTOMER/SUPPLIER eligibility after Party INACTIVE;
- Finance proof that deactivation creates no ledger/balance effect;
- stale multi-client Party state mutation;
- performance/load/security regression;
- backup/restore.

## Decision

The next dependency-minimal Parties implementation work package is:

**PARTY-IMP-005 — Deactivate Party**

Status:
- IMPLEMENTED / COMPLETED.

Canonical completion evidence:
- `docs/plan/03-cariler/party-imp-005-implementation.md`
- Foundation Build run `35922536747` — SUCCESS
- Foundation Test Deploy run `35922536768` — SUCCESS

Party reactivation, fuzzy duplicate review, Contact/Communication/Address, Merge, External Mapping, Tax Identity follow-up and Finance/Sales/Purchasing behavior remain outside this slice.
