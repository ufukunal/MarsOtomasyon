# P5 Parties — Third Vertical Slice Readiness

Status: IMPLEMENTED / COMPLETED
Date: 2026-09-23
Phase: P5 — Core application implementation
Module: Parties
Implementation work package: PARTY-IMP-003
Title: Add Turkish Tax Identity

## Objective

Freeze the smallest coherent Parties vertical slice after PARTY-IMP-002.

Selected slice:

**Add Turkish Tax Identity**

The command adds one ACTIVE Turkish VKN or TCKN identity to one existing Party in the trusted company. It introduces deterministic tax-identity collision authority without implementing provider/GİB enrollment, checksum verification, fuzzy duplicate scoring, tax-identity edit/deactivate, or legal-document send validation.

## Candidate comparison

### Turkish Tax Identity — SELECTED

Why:
- Party 1→N Tax Identity is frozen by PLAN-003 and PLAN-010;
- tax identity is a distinct authoritative child entity;
- deterministic company + scheme + normalized value collision prevention is explicitly required;
- `party.tax_identity.manage` already exists;
- PARTY-IMP-001 supplies Party/company identity and ADR-0005 permission authority;
- PARTY-IMP-002 proves the existing child-entity + Party lookup + audit/idempotency transaction pattern;
- a TR-only first slice can use the repository-frozen VKN=10 digits and TCKN=11 digits structural rules without inventing checksum/provider behavior;
- no Finance/stock/account/cash/cost posting exists;
- independently testable through API + the existing Party create journey.

### Soft duplicate candidate/review — deferred

Why not next:
- fuzzy/name/contact/address candidate matching remains warning/review input;
- no accepted algorithm, normalization, score or threshold is frozen;
- tax-identity deterministic collision can be implemented without inventing fuzzy behavior.

### Contacts / Addresses — deferred

Why not next:
- introduces multiple authoritative child types and broader purpose/default/lifecycle UI;
- Address also carries billing/shipping/general semantics and downstream snapshot use;
- larger migration/UI surface than one Tax Identity child.

### Role lifecycle after activation — deferred

Why not next:
- deactivation/reactivation is coherent but extends a state machine already proven by PARTY-IMP-002 rather than adding a missing authoritative Party identity capability;
- reactivation/selection effects require additional lifecycle interaction testing;
- it is not a prerequisite for Tax Identity.

### Party lifecycle / Merge — deferred

Why not next:
- Party deactivate/reactivate reruns duplicate/legal identity validation;
- merge requires conflict review across roles/tax/contact/address and survivor lineage;
- materially broader and higher-risk.

## Frozen invariants

- Party remains company-scoped authority.
- Tax Identity belongs to exactly one Party.
- Party may have many Tax Identities.
- Tax Identity company scope follows the owning Party.
- request cannot choose CompanyId.
- first slice jurisdiction is exactly TR.
- accepted first-slice schemes are VKN and TCKN.
- VKN value is exactly 10 ASCII digits.
- TCKN value is exactly 11 ASCII digits.
- no checksum algorithm is claimed or invented.
- no provider/GİB enrollment or verification status is claimed.
- one ACTIVE company + jurisdiction + scheme + value cannot identify competing Parties.
- deterministic collision is a hard conflict, not a soft warning.
- Party Code and tax identity are separate authorities.
- historical document snapshots are not mutated.
- tax identity is sensitive data; ordinary read/export permission cannot bypass tax-identity authorization.
- no Finance/Account/Cash/Bank/Stock/Reservation/Cost effect.
- ADR-0005 remains ERP permission authority.

## Physical DB contract

Owning module:
- Parties.

New authoritative entity:
- Tax Identity.

Physical schema:
- existing `parties`.

Candidate table:
- `parties.tax_identities`.

Columns:
- `id`: BIGINT-style surrogate PK;
- `public_id`: UUID public identity;
- `party_id`: required Party FK;
- `company_id`: required trusted company scope used for durable company-level collision authority;
- `jurisdiction`: TR in this slice;
- `scheme`: VKN or TCKN;
- `value`: canonical structural value; for this slice digits-only means stored value is already normalized;
- `state`: ACTIVE initially; INACTIVE representation reserved for later lifecycle compatibility;
- `version`: positive optimistic concurrency token, initial 1;
- `created_at`: existing timestamp convention.

Company relationship:
- Tax Identity cannot rely only on application code for company compatibility because uniqueness is company-wide across Parties.
- add a Party alternate unique key/index on `id + company_id` solely to support a composite FK;
- Tax Identity `party_id + company_id` references Party `id + company_id`;
- this prevents a child row from pairing one Party id with another trusted company.

Constraints:
- PK on id;
- unique public id;
- composite Party/company FK with delete RESTRICT;
- jurisdiction check = TR;
- scheme check = VKN/TCKN;
- structural value check:
  - VKN → exactly 10 digits;
  - TCKN → exactly 11 digits;
- state check ACTIVE/INACTIVE;
- version > 0;
- partial unique active identity:
  - company_id + jurisdiction + scheme + value WHERE state = ACTIVE.

Indexes:
- public id unique;
- active deterministic collision unique index above;
- Party FK/index for Party → Tax Identity traversal.
- no speculative fuzzy/name index.

Migration:
- additive;
- adds one Party alternate key/index required by the composite FK;
- creates Tax Identity table/indexes/checks;
- no backfill;
- no existing Party mutation;
- no destructive operation;
- generated through existing EF Core migration path;
- TEST rehearsal required.

## Domain / Application contract

Domain:
- `TaxIdentityScheme.Vkn`;
- `TaxIdentityScheme.Tckn`;
- `TaxIdentityState.Active` / future-compatible `Inactive`;
- public UUID;
- Party public UUID + trusted CompanyId;
- structural validation only.

Command:
- `AddPartyTaxIdentity`.

Input:
- Party public UUID;
- jurisdiction;
- scheme;
- value;
- idempotency key.

Trusted context:
- ActorId;
- CompanyId;
- optional BranchId carried by Foundation but not Tax Identity authority;
- CorrelationId.

Application checks:
- `party.tax_identity.manage`;
- Party public UUID required;
- jurisdiction must be TR;
- scheme VKN/TCKN;
- ASCII digits only;
- exact length by scheme;
- durable idempotency key;
- Party exists in trusted company;
- deterministic active identity collision maps to conflict.

Result:
- Tax Identity public UUID;
- Party public UUID;
- jurisdiction;
- scheme;
- ACTIVE state;
- version;
- correlation id.

The result does not echo the raw sensitive identity value.

## Authorization / sensitive-data boundary

Permission:
- `party.tax_identity.manage` for add.

Not implied:
- `party.tax_identity.read`;
- `party.tax_identity.read_full`;
- `party.export`.

The add endpoint does not introduce a list/read/export API, so no new full-value disclosure path is created.

UI entry may accept the full value because the actor is performing the manage action, but after success the raw value is cleared and success state does not re-render the full value.

Audit:
- raw VKN/TCKN is NOT written to audit action/reason/payload.

## Transaction / audit / idempotency / outbox

One PostgreSQL transaction:
1. resolve Party by trusted CompanyId + Party public UUID;
2. insert Tax Identity;
3. insert Foundation idempotency operation;
4. append audit without raw tax value;
5. complete idempotency;
6. commit.

Audit:
- required;
- actor/company/correlation;
- Party public UUID;
- Tax Identity public UUID may be recorded as technical entity identity if supported by current audit contract;
- action identifies scheme but never raw value.

Idempotency:
- required for retryable POST;
- company-scoped Tax Identity add operation;
- duplicate operation → conflict.

Deterministic collision:
- active company + TR + scheme + value unique authority;
- collision → conflict;
- cannot be overridden by duplicate-review permission.

Outbox:
- NOT REQUIRED in PARTY-IMP-003;
- `PartyTaxIdentityChanged` remains a candidate event only;
- no accepted consumer currently requires it.

## API contract

Endpoint:
- `POST /api/v1/parties/{partyPublicId}/tax-identities`.

Headers:
- `Idempotency-Key`: required.

Body:
- `jurisdiction`: TR;
- `scheme`: VKN or TCKN;
- `value`: 10/11 digit value.

No CompanyId field.

Expected mapping:
- unauthenticated → 401;
- authenticated without `party.tax_identity.manage` → 403;
- invalid jurisdiction/scheme/value/idempotency → 400;
- Party absent from trusted company → 404;
- duplicate operation → 409;
- active deterministic tax identity collision → 409;
- success → 201.

No read/list/full-value endpoint is added in this slice.

## UI contract

Reuse:
- existing `/parties/new` journey after successful Party creation.

Add a Tax Identity section:
- jurisdiction fixed/read-only TR;
- scheme select VKN/TCKN;
- sensitive value input;
- helper text:
  - VKN 10 digits;
  - TCKN 11 digits;
- explicit statement that structural validity does not prove GİB/e-document enrollment;
- add action uses a fresh idempotency key;
- success clears the raw value input and shows scheme + ACTIVE state only;
- 401/403/404/409/validation errors remain explicit;
- failure does not erase the Party or activated roles.

Not added:
- tax identity list/read API;
- full-value reveal;
- verification provider status;
- edit/deactivate;
- e-document enrollment lookup.

## Current-official-rule boundary

No web/current-provider rule is required for PARTY-IMP-003 acceptance because the slice does not claim:
- checksum validity;
- taxpayer existence;
- e-Fatura/e-Arşiv enrollment;
- provider verification;
- legal-document send readiness.

Only repository-frozen structural rules VKN=10 digits and TCKN=11 digits are enforced.

Current official rules must be reverified in the later integration/legal-document workflow that claims those semantics.

## Targeted verification

Normal development:
- .NET Release build;
- Tax Identity domain structural tests;
- VKN 10-digit valid shape;
- TCKN 11-digit valid shape;
- reject non-digit/length/jurisdiction/scheme;
- missing `party.tax_identity.manage` denies before persistence;
- trusted CompanyId scope;
- Party-not-found mapping;
- duplicate operation mapping;
- deterministic active tax identity collision mapping;
- audit contains no raw VKN/TCKN;
- EF composite Party/company FK;
- EF active unique collision index;
- EF public UUID uniqueness;
- EF concurrency token/checks;
- generated additive migration safety;
- EF pending-model check;
- frontend typecheck/tests/build;
- Web request contains no CompanyId;
- Web success clears sensitive input;
- API/OpenAPI smoke;
- TEST migration/deploy/readiness/smoke.

Remote smoke may prove:
- /parties/new 200;
- unauthenticated tax-identity POST 401;
- health/readiness 200;
- OpenAPI route present.

A real authenticated Tax Identity mutation is not required for normal TEST smoke unless an existing safe principal/grant path exists; no auth bypass may be invented.

## Full Test Day pending

- concurrent same VKN/TCKN add from two requests;
- cross-company same identity acceptance/isolation;
- broad tax-identity permission matrix;
- authenticated browser add flow;
- future edit/deactivate vs document snapshot race;
- future verification-provider reconciliation;
- high-volume tax identity lookup;
- PII logging/export/security regression;
- cross-company IDOR;
- performance/load.

## Decision

The next dependency-minimal Parties implementation work package is:

**PARTY-IMP-003 — Add Turkish Tax Identity**

Status:
- IMPLEMENTED / COMPLETED.

Canonical completion evidence:
- `docs/plan/03-cariler/party-imp-003-implementation.md`
- Foundation Build run `35894175633` — SUCCESS
- Foundation Test Deploy run `35894175558` — SUCCESS

No generic non-TR tax scheme, checksum/provider verification, tax-identity read/full-value endpoint, contact, address, fuzzy duplicate review, lifecycle/merge or Finance behavior is included.
