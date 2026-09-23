# PARTY-IMP-005 — Deactivate Party

Status: COMPLETED
Date: 2026-09-24

## Objective

Implement the dependency-minimal fifth Parties vertical slice after PARTY-IMP-004:

- change one existing company-scoped Party from ACTIVE to INACTIVE;
- require Mars-owned server-side `party.deactivate`;
- require a mandatory reason;
- protect the state change with expected-version optimistic concurrency;
- preserve roles, Tax Identities, history and Finance separation;
- write audit + durable PostgreSQL idempotency atomically;
- expose a protected deactivation API;
- extend the existing `/parties/new` journey with deactivation of the newly created Party;
- prove no EF model change/new migration is needed;
- verify through targeted build/tests and remote TEST deployment/smoke.

This package does not implement Party reactivation, soft duplicate review, Contacts/Addresses, Merge, External Mapping, Tax Identity lifecycle or consuming Sales/Purchasing eligibility enforcement.

## Scope selection

The remaining Parties candidates after PARTY-IMP-004 were compared against frozen PLAN-003 + PLAN-010.

Party deactivation was selected because:
- ACTIVE → INACTIVE is already a frozen explicit action;
- `party.deactivate` is an accepted distinct permission;
- mandatory reason + audit are already frozen;
- `parties.parties` already owns state + optimistic version;
- no new entity/table/column/index is required;
- no fuzzy matching, provider or current legal rule is needed;
- history remains readable and existing transactions are not reversed;
- no Finance/stock/account/cash/cost authority is introduced.

Reactivation was deliberately excluded because:
- it has the separate `party.reactivate` permission;
- frozen PLAN-003 requires duplicate/legal identity validation to rerun;
- soft duplicate candidate/review is not implementation-ready because no accepted normalization/similarity/score/threshold exists.

Other candidates remain broader or under-specified:
- Contact Person / Communication Point / Address create new authoritative child models;
- External Mapping requires provider/account/system physical normalization/scope decisions;
- Merge requires conflict review across Party children;
- Tax Identity follow-up needs separately frozen sensitive read/lifecycle/provider contracts.

## Repository / tested state

Repository:
- `ufukunal/MarsOtomasyon`

Branch:
- `main`

Starting HEAD:
- `aac8e38786ced0ecdc198ff843b2c547d253b746`

Readiness commit:
- `73cf3921becc615ef56e8c871b67882fa8ef411b`

Implementation / tested deployment commit:
- `4ab2a30bfffdf64c9e5b7634708ad4cdacdde33a`

Foundation Build:
- run `35922536747`
- job `107389743246`
- SUCCESS.

Foundation Test Deploy:
- run `35922536768`
- job `107389744421`
- SUCCESS.

## Frozen semantics implemented

- Party remains company-scoped authoritative identity.
- Party state remains ACTIVE / INACTIVE / MERGED.
- PARTY-IMP-005 accepts ACTIVE → INACTIVE only.
- INACTIVE is not hard delete.
- MERGED cannot be deactivated by this command.
- Deactivation requires `party.deactivate`.
- Deactivation requires a non-empty bounded reason.
- CompanyId comes only from trusted execution context.
- Request cannot supply CompanyId.
- Party roles are not mutated.
- Tax Identities are not mutated.
- Historical document snapshots are not rewritten.
- Existing transactions are not cancelled or reversed.
- The Party becomes unavailable for new counterparty selection by frozen policy, but consuming-module eligibility enforcement is outside this slice.
- No Finance/Account/Cash/Bank/Stock/Reservation/Cost posting effect.
- ADR-0005 remains ERP permission authority.

## Physical database contract

No EF model change was made.

Existing table:
- `parties.parties`.

Existing fields reused:
- `public_id`;
- `company_id`;
- `state`;
- `version`.

Existing constraints remain authoritative:
- state in Active / Inactive / Merged;
- version > 0;
- optimistic concurrency token on Version.

No new:
- table;
- column;
- index;
- FK;
- check constraint;
- migration.

Final EF pending-model verification:
- PASS;
- “No changes have been made to the model since the last migration.”

Committed migration count:
- 5 before PARTY-IMP-005;
- 5 after PARTY-IMP-005.

No no-op migration was created.

## Application

Added:
- `src/Mars.Application/Parties/DeactivateParty/DeactivateParty.cs`.

Command:
- `DeactivatePartyCommand`.

Input:
- Party public UUID;
- expected positive Party version;
- mandatory reason;
- idempotency key.

Authorization:
- `party.deactivate` checked before persistence.

Validation:
- Party UUID required;
- expected version > 0;
- durable bounded Idempotency-Key;
- reason required;
- reason trimmed and bounded to the Foundation audit persistence limit.

Persistence outcomes:
- Deactivated;
- PartyNotFound;
- DuplicateOperation;
- StaleVersion;
- AlreadyInactive;
- MergedStateConflict.

Receipt:
- Party public UUID;
- INACTIVE;
- resulting incremented version;
- correlation id.

## Persistence / transaction

Added:
- `src/Mars.Infrastructure/Persistence/Parties/EfPartyDeactivatePersistence.cs`.

One PostgreSQL transaction:
1. resolve Party by public UUID + trusted CompanyId;
2. compare expected version;
3. reject already INACTIVE;
4. reject MERGED;
5. update ACTIVE → INACTIVE;
6. increment version;
7. add durable idempotency operation;
8. append audit;
9. save;
10. mark idempotency succeeded;
11. commit.

Concurrency:
- expected-version mismatch → stale conflict;
- EF `DbUpdateConcurrencyException` after read → stale conflict;
- both map to Application Concurrency / HTTP 409.

Idempotency:
- company-scoped scope `parties.deactivate:{companyId}`;
- duplicate Foundation idempotency unique constraint → conflict.

No Party Role or Tax Identity record is part of the write contract.

## Audit

Action:
- `PartyDeactivated`.

Audit includes:
- trusted actor;
- trusted company;
- branch when present;
- correlation id;
- Party public UUID;
- mandatory deactivation reason.

No Finance payload or sensitive Tax Identity value is added.

## Authorization / security

Permission:
- `party.deactivate`.

Enforcement:
- API policy;
- Application handler.

Preserved boundaries:
- authentication alone is insufficient;
- Party UUID route parameter is not company authority;
- persistence lookup requires trusted CompanyId;
- request has no CompanyId;
- UI visibility/state is not authorization;
- no cross-company Party mutation;
- no Finance permission or authority leakage.

## API

Added request:
- `src/Mars.Api/Parties/DeactivatePartyRequest.cs`.

Endpoint:
- `POST /api/v1/parties/{partyPublicId}/deactivate`.

Header:
- `Idempotency-Key`: required.

Body:
- `version`: expected positive Party version;
- `reason`: required.

Mappings:
- unauthenticated → 401;
- authenticated without `party.deactivate` → 403;
- invalid Party/version/reason/idempotency → 400;
- Party absent in trusted company → 404;
- duplicate operation → 409;
- stale version → 409;
- already INACTIVE → 409;
- MERGED → 409;
- success → 200.

Reactivation endpoint is not added.

## Mars.Web

Modified:
- `src/Mars.Web/src/party-create.ts`.

Existing route reused:
- `/parties/new`.

After successful Party creation:
- Party public UUID + current Party version remain in page state;
- Party status/deactivation section becomes visible;
- a reason is required;
- request uses current expected Party version;
- request uses a fresh idempotency key;
- request contains no CompanyId;
- success updates visible state to INACTIVE;
- returned version replaces page-state Party version;
- reason field is cleared;
- deactivation control becomes disabled.

Existing Party Role and Tax Identity state is not erased.

No reactivation UI is added.

The page still does not claim to be a general existing-Party detail/editor because a Party read/detail surface has not been implemented.

## Verification evidence

### Foundation Build — SUCCESS

Run:
- `35922536747`

Job:
- `107389743246`

Evidence:
- frontend tests: 16 / 16 PASS;
- Party deactivation Web request contract/no client CompanyId: PASS;
- .NET Release build: PASS;
- warnings: 0;
- errors: 0;
- Foundation targeted tests: 55 / 55 PASS;
- missing `party.deactivate` denied before persistence: PASS;
- Party/version/reason validation: PASS;
- trusted-company audit/idempotency write: PASS;
- PartyNotFound/DuplicateOperation/StaleVersion/AlreadyInactive/Merged mapping: PASS;
- INACTIVE receipt with incremented version: PASS;
- migration Up safety count: 5;
- EF pending-model: PASS — no model changes since last migration;
- API smoke: PASS;
- project references: PASS.

### Foundation Test Deploy — SUCCESS

Run:
- `35922536768`

Job:
- `107389744421`

Pre-deploy:
- frontend tests: 16 / 16 PASS;
- Release build: PASS, 0 warnings / 0 errors;
- Foundation targeted tests: 55 / 55 PASS;
- migration safety: 5 committed migrations PASS;
- EF model drift: PASS;
- remote TEST preflight: PASS;
- migration count before deploy: 5.

Remote environment evidence:
- hostname: `mars-prod`;
- Ubuntu 24.04.5 LTS;
- Docker 29.8.0;
- Docker Compose 5.5.1;
- PostgreSQL master/runtime role login: PASS;
- runtime privilege model: PASS;
- remote PostgreSQL network login: PASS;
- preflight result: PASS.

Remote deploy:
- API image build: PASS;
- migrator image build: PASS;
- Web image build: PASS;
- database update: PASS with no new PARTY-IMP-005 migration;
- runtime grants: PASS;
- Compose config: PASS;
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
- unauthenticated Party deactivate POST → 401;
- unauthenticated Party Role activation POST → 401;
- unauthenticated Party Role lifecycle POST → 401;
- unauthenticated Party Tax Identity POST → 401;
- OpenAPI → 200;
- smoke result: PASS.

A real authenticated Party deactivation against TEST is not claimed.

## Verification history

No implementation/build/test defect was found on the PARTY-IMP-005 implementation commit.

While the TEST workflow was still running, an attempt to retrieve the live job log returned a GitHub temporary BlobNotFound/404 because the log artifact had not been finalized. The workflow itself remained in progress and later completed SUCCESS. This was a tooling/log-availability event, not an implementation or deployment failure.

No failed verification run is hidden.

## Explicitly deferred

- Party reactivate;
- accepted soft duplicate candidate/review;
- Contact Person;
- Communication Point;
- Address;
- Party Merge;
- Party External Mapping;
- Tax Identity read/read_full/edit/deactivate/provider/non-TR;
- Sales/Purchasing Party eligibility implementation;
- Finance integration.

## Full Test Day pending

- concurrent deactivate/deactivate;
- deactivation racing Party edit;
- deactivation racing role/tax mutation;
- deactivation racing Sales/Purchasing document creation;
- cross-company IDOR;
- broad permission matrix;
- authenticated browser create → deactivate;
- existing CUSTOMER/SUPPLIER eligibility after Party INACTIVE;
- Finance proof that deactivation creates no ledger/balance effect;
- stale multi-client Party state mutation;
- performance/load/security regression;
- backup/restore.

## Completion decision

PARTY-IMP-005 is COMPLETED.

Completion evidence exists for:
- source-backed dependency-minimal scope;
- implementation;
- permission/company/audit/idempotency/concurrency contracts;
- frontend deactivation behavior;
- no-EF-model-change proof;
- Release build + targeted tests;
- remote TEST deployment;
- unauthenticated security boundary;
- OpenAPI and runner-to-TEST smoke;
- migration count remaining 5.

## Planning progress

Unchanged:
- Master Section-8 planning coverage: 9 / 30 = 30.0%;
- P2 core commercial planning: 8 / 8 = 100.0%.

These are planning-coverage metrics, not implementation-progress metrics.

## Next safe action

Remaining Parties work includes:
- Party reactivation;
- accepted soft duplicate candidate/review;
- Contact Person / Communication Point / Address;
- Party External Mapping;
- Party Merge;
- broader Tax Identity lifecycle/provider/non-TR support.

Repository does not freeze the next implementation order or a PARTY-IMP-006 scope.

Next task:
- define the next smallest coherent Parties vertical slice from frozen PLAN-003 + PLAN-010;
- do not assign PARTY-IMP-006 until exact scope is source-backed and frozen.
