# PARTY-IMP-003 — Add Turkish Tax Identity

Status: COMPLETED
Date: 2026-09-23

## Objective

Implement the dependency-minimal third Parties vertical slice after PARTY-IMP-002:

- add one ACTIVE Turkish Tax Identity to an existing company-scoped Party;
- support TR + VKN/TCKN structural identity only;
- enforce deterministic company-level active identity collision;
- reuse ADR-0005 Mars-owned PostgreSQL permission authority with `party.tax_identity.manage`;
- keep raw VKN/TCKN out of audit and success receipts;
- provide protected add API and extend the existing `/parties/new` journey;
- write audit + durable idempotency atomically with Tax Identity persistence;
- generate/apply an additive EF migration;
- verify through targeted build/tests and remote TEST deployment/smoke.

This work package does not claim VKN/TCKN checksum validity, GİB/e-document enrollment, provider verification or generic non-TR Tax Identity support.

## Scope selection

Remaining Party candidates after PARTY-IMP-002 were compared against frozen PLAN-003 + PLAN-010:
- Tax Identity;
- soft duplicate candidate/review;
- contacts/addresses;
- role lifecycle;
- Party lifecycle/merge.

Tax Identity was selected because:
- Party 1→N Tax Identity is already frozen;
- deterministic company + jurisdiction + scheme + value collision prevention is explicit;
- accepted permission `party.tax_identity.manage` already exists;
- one authoritative child entity is sufficient;
- PARTY-IMP-001/002 company/permission/audit/idempotency patterns are reusable;
- VKN=10 digits and TCKN=11 digits are frozen structural rules;
- fuzzy duplicate algorithms, broader contact/address lifecycle and merge dependencies are not required.

## Repository / tested state

Repository:
- `ufukunal/MarsOtomasyon`

Branch:
- `main`

Starting HEAD:
- `bad6364956e8825a1c22038cec6494113e7cd99d`

Readiness document:
- `docs/plan/03-cariler/p5-third-slice-readiness.md`

Initial implementation commit:
- `cb22e1fca1d1cd0ebeab98acbd3db1644297c0f8`

Compile-fix commit:
- `a94bea03790604637979bdbd693d3809b386a359`

Clean model/migration preparation:
- `b2247d810c1671caa969d8c5854d266dccfe7fd6`

Final generated migration commit:
- `70876a0fcb84368d9b3ec9d305eaf8eacf0c19b2`

Final tested verification/deployment commit:
- `ec5f2c932da8f717e7e56d1e612ea0ba2dbaa36c`

Foundation Build:
- run `35894175633`
- job `107293838961`
- SUCCESS.

Foundation Test Deploy:
- run `35894175558`
- job `107293839344`
- SUCCESS.

## Frozen semantics implemented

- Party remains company-scoped authority.
- Tax Identity belongs to exactly one Party.
- First-slice jurisdiction is TR only.
- First-slice schemes are VKN and TCKN.
- VKN requires exactly 10 ASCII digits.
- TCKN requires exactly 11 ASCII digits.
- Tax Identity is initially ACTIVE.
- No checksum algorithm is implemented.
- No GİB/provider enrollment or verification status is claimed.
- Deterministic active identity collision is a hard conflict.
- Company scope comes from trusted execution context / owning Party, not request JSON.
- No Finance/Account/Cash/Bank/Stock/Reservation/Cost effect.
- Historical document snapshots remain unaffected.
- Raw VKN/TCKN is not exposed in success receipt or audit metadata.

## Domain / Application

Created:
- `src/Mars.Domain/Parties/PartyTaxIdentity.cs`
- `src/Mars.Application/Parties/AddPartyTaxIdentity/AddPartyTaxIdentity.cs`

Added:
- `PartyTaxIdentityScheme.Vkn`
- `PartyTaxIdentityScheme.Tckn`
- `PartyTaxIdentityState.Active`
- future-compatible `Inactive`
- structural domain validation;
- `AddPartyTaxIdentityCommand`;
- `AddPartyTaxIdentityReceipt`;
- persistence-neutral write contract/outcomes;
- `AddPartyTaxIdentityHandler`.

Authorization:
- handler requires `party.tax_identity.manage` before persistence.

Validation:
- non-empty Party public UUID;
- required bounded Idempotency-Key;
- TR only;
- VKN/TCKN only;
- ASCII digits only;
- exact 10/11 digit lengths.

Receipt:
- includes public Tax Identity UUID, Party UUID, jurisdiction, scheme, state, version, correlation;
- deliberately excludes raw Tax Identity value.

## Persistence / physical DB

Created:
- `PartyTaxIdentityRecord`;
- `PartyTaxIdentityConfiguration`;
- `EfPartyTaxIdentityAddPersistence`.

Table:
- `parties.tax_identities`.

Columns:
- `id` bigint identity;
- `public_id` UUID;
- `party_id` bigint;
- `company_id` UUID;
- `jurisdiction`;
- `scheme`;
- `value`;
- `state`;
- `version`;
- `created_at`.

Party model gains alternate key:
- `ak_parties_id_company` on `id + company_id`.

Tax Identity FK:
- `party_id + company_id` → Party `id + company_id`;
- delete RESTRICT.

This prevents a Tax Identity child from pairing a valid Party id with a different company.

Constraints:
- unique public UUID;
- jurisdiction = TR;
- scheme Vkn/Tckn;
- structural VKN/TCKN digit-length check;
- state Active/Inactive;
- version > 0;
- partial unique active identity:
  `company_id + jurisdiction + scheme + value WHERE state = 'Active'`.

Indexes:
- EF-generated composite FK index on `party_id + company_id`;
- active collision unique index;
- public UUID unique index.

A first generated migration contained an additional redundant `party_id` index. It was never deployed. The model/snapshot was reset and the migration regenerated through EF tooling so the final migration contains only the necessary composite FK index.

## Transaction

`EfPartyTaxIdentityAddPersistence` performs in one PostgreSQL transaction:
1. resolve Party by trusted CompanyId + Party public UUID;
2. insert Tax Identity;
3. insert durable Foundation idempotency operation;
4. append audit;
5. save;
6. complete idempotency state;
7. commit.

Mapped database conflicts:
- Foundation idempotency unique constraint → duplicate operation;
- active Tax Identity unique constraint → deterministic Tax Identity conflict.

## Authorization / sensitive-data boundary

Permission:
- `party.tax_identity.manage`.

Enforced:
- API policy;
- Application handler.

Not introduced:
- Tax Identity list/read endpoint;
- full-value reveal endpoint;
- export endpoint;
- client CompanyId authority.

Frozen permissions `party.tax_identity.read` and `party.tax_identity.read_full` are therefore not consumed by this add-only slice.

Audit:
- identifies action/scheme and Party;
- raw VKN/TCKN is not written.

UI:
- accepts raw value only for the manage action;
- clears the input after success;
- does not re-render the raw identity in success state.

## API

Created request:
- `src/Mars.Api/Parties/AddPartyTaxIdentityRequest.cs`.

Endpoint:
- `POST /api/v1/parties/{partyPublicId}/tax-identities`.

Body:
- `jurisdiction`;
- `scheme`;
- `value`.

No CompanyId field.

Expected mapping:
- unauthenticated → 401;
- missing permission → 403;
- invalid structural input → 400;
- Party absent in trusted company → 404;
- duplicate operation → 409;
- deterministic active Tax Identity collision → 409;
- success → 201.

## Mars.Web

Modified:
- `src/Mars.Web/src/party-create.ts`.

Existing route reused:
- `/parties/new`.

After Party creation:
- Tax Identity section becomes available;
- jurisdiction is fixed/displayed as TR;
- VKN/TCKN can be selected;
- sensitive value uses numeric input semantics;
- UI states explicitly that structural validity does not prove GİB/e-document enrollment;
- request contains no CompanyId;
- fresh idempotency key is used;
- successful add clears the raw value;
- status displays scheme + ACTIVE + correlation only;
- failure does not erase Party or activated roles.

## Migration

Final generated migration:
- `src/Mars.Infrastructure/Persistence/Migrations/Parties/20260923171142_PartyImp003TurkishTaxIdentity.cs`
- generated Designer;
- updated `MarsDbContextModelSnapshot.cs`.

Migration commit:
- `70876a0fcb84368d9b3ec9d305eaf8eacf0c19b2`.

Final migration:
- additive;
- adds Party alternate key;
- creates Tax Identity table/constraints/indexes;
- no data backfill;
- no destructive existing Party rewrite.

Migration helper final state:
- manual `workflow_dispatch`;
- `contents: read`;
- artifact generation only;
- no persistent auto-commit/write behavior.

## Verification evidence

### Foundation Build — SUCCESS

Run:
- `35894175633`

Job:
- `107293838961`

Final evidence:
- frontend targeted tests: 14 / 14 PASS;
- Tax Identity Web contract/no client CompanyId/sensitive-clear behavior: PASS;
- .NET Release build: PASS;
- warnings: 0;
- errors: 0;
- Foundation targeted tests: 45 / 45 PASS;
- VKN/TCKN structural invariants: PASS;
- missing `party.tax_identity.manage` denied before persistence: PASS;
- trusted company audit/idempotency and raw-value exclusion: PASS;
- not-found/duplicate mapping: PASS;
- EF company collision/concurrency contracts: PASS;
- EF pending-model check: PASS — no model changes since last migration;
- API smoke: PASS;
- project-reference gate: PASS.

### Foundation Test Deploy — SUCCESS

Run:
- `35894175558`

Job:
- `107293839344`

Pre-deploy:
- frontend tests: 14 / 14 PASS;
- Release build: PASS, 0 warnings / 0 errors;
- Foundation targeted tests: 45 / 45 PASS;
- migration safety: PASS for 5 committed migrations;
- EF model drift: PASS;
- remote TEST preflight: PASS;
- migration count before deploy: 4.

Remote deploy:
- API image build: PASS;
- migrator image build: PASS;
- Web image build: PASS;
- migration `20260923171142_PartyImp003TurkishTaxIdentity`: APPLIED;
- migration: PASS;
- runtime grants: PASS;
- Compose validation: PASS;
- health gate: PASS;
- migration count after deploy: 5;
- deploy result: PASS.

Runner-to-TEST smoke:
- GET `/` → 200;
- GET `/components` → 200;
- GET `/proof` → 200;
- GET `/parties/new` → 200;
- GET `/health/live` → 200;
- GET `/health/ready` → 200;
- unauthenticated Foundation context → 401;
- unauthenticated Foundation proof POST → 401;
- unauthenticated Party create POST → 401;
- unauthenticated Party Role POST → 401;
- unauthenticated Party Tax Identity POST → 401;
- OpenAPI → 200;
- smoke result: PASS.

A real authenticated Tax Identity mutation against TEST is not claimed.

## Failed verification history

### Initial implementation runs

Commit:
- `cb22e1fca1d1cd0ebeab98acbd3db1644297c0f8`

Foundation Build:
- run `35893323819`
- frontend 14 / 14 PASS;
- C# build failed with CS1003 in Tax Identity filtered-index string literal.

Foundation Test Deploy:
- run `35893323662`
- frontend PASS;
- same compile failure;
- remote deployment was not reached.

Correction:
- commit `a94bea03790604637979bdbd693d3809b386a359`;
- corrected EF filter string literal only.

### Missing migration runs

Corrected source commit:
- `a94bea03790604637979bdbd693d3809b386a359`.

Foundation Build:
- run `35893464723`.

Foundation Test Deploy:
- run `35893464667`.

Passed before failure:
- frontend;
- .NET build;
- 45 / 45 targeted Foundation tests.

Failure:
- expected EF model drift because PARTY-IMP-003 migration was not committed.

Correction:
- generate migration with repository EF tooling.

### First generated migration refinement

Migration generation run:
- `35893591447` — SUCCESS.

Generated commit:
- `993a4d7cd5105a93fa633d3df50b9e768e220e23`.

Inspection found:
- EF composite FK index plus an unnecessary explicit single-column PartyId index.

The migration had not been deployed.

Correction:
- remove the redundant model index;
- restore the pre-migration snapshot;
- remove the first generated migration;
- regenerate entirely through EF tooling.

Intermediate cleanup commit:
- `b2247d810c1671caa969d8c5854d266dccfe7fd6`.

Expected intermediate CI:
- Foundation Build run `35893843802`: build/tests PASS, model-drift gate failed because migration was intentionally removed for regeneration.
- Test Deploy run `35893843846`: build/tests PASS, model-drift gate failed; remote deploy not reached.

Clean regeneration:
- run `35893874001` — SUCCESS;
- final generated migration commit `70876a0fcb84368d9b3ec9d305eaf8eacf0c19b2`.

No failed run is hidden.

## Security / privacy boundaries

Preserved:
- authentication is not authorization;
- ADR-0005 remains permission authority;
- CompanyId is trusted and not client-authoritative;
- raw Tax Identity is not returned in success receipt;
- raw Tax Identity is not placed in audit metadata;
- no list/read/full-value API introduced;
- no checksum/provider/GİB validation is falsely claimed;
- no fuzzy matching invented;
- no cross-company sharing;
- no Finance authority moved into Parties;
- no production deployment/secret decision.

## Explicitly deferred

- VKN/TCKN checksum validation;
- GİB/e-Fatura/e-Arşiv enrollment lookup;
- provider verification;
- generic non-TR Tax Identity schemes;
- Tax Identity read/list/read_full;
- Tax Identity edit/deactivate/verification lifecycle;
- soft duplicate candidate/review;
- Contact/Communication;
- Address;
- Party/role lifecycle beyond current activation;
- Merge;
- Finance/Sales/Purchasing integration.

## Full Test Day pending

- concurrent same VKN/TCKN insert race;
- cross-company same Tax Identity isolation;
- broad Tax Identity permission matrix;
- authenticated browser Tax Identity add;
- cross-company IDOR;
- future edit/deactivate vs document snapshot race;
- provider reconciliation when implemented;
- high-volume identity lookup;
- PII logging/export/security regression;
- performance/load;
- backup/restore.

## Completion decision

PARTY-IMP-003 is COMPLETED.

Evidence exists for:
- dependency-minimal frozen scope;
- implementation;
- generated committed clean migration;
- structural/privacy/company/authorization targeted tests;
- build/model-drift verification;
- remote TEST migration;
- readiness/health;
- unauthenticated API security boundary;
- runner-to-TEST smoke.

## Planning progress

Unchanged:
- Master section-8 planning: 9 / 30 = 30.0%;
- P2 core commercial planning: 8 / 8 = 100.0%.

These are planning-coverage metrics, not implementation-progress metrics.

## Next safe action

Remaining Party follow-up scope includes:
- accepted soft duplicate candidate/review;
- contacts/addresses;
- role lifecycle after activation;
- Party lifecycle/merge;
- broader Tax Identity lifecycle/provider/non-TR support where later required.

Repository does not yet freeze the next ordering or a PARTY-IMP-004 scope.

Next task:
- define the next smallest coherent Parties vertical slice;
- do not assign PARTY-IMP-004 until exact scope is frozen.
