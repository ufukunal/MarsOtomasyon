# PARTY-IMP-004 — Manage Party Role Lifecycle

Status: COMPLETED
Date: 2026-09-23

## Objective

Complete the existing CUSTOMER/SUPPLIER Party Role lifecycle after PARTY-IMP-003 without creating a new Party identity, schema authority or Finance effect.

Implemented lifecycle:
- existing role ACTIVE → INACTIVE;
- existing role INACTIVE → ACTIVE;
- server-side `party.role.manage`;
- trusted-company Party/role lookup;
- optimistic expected-version concurrency;
- mandatory deactivation reason;
- audit + durable PostgreSQL idempotency;
- protected API;
- lifecycle controls in the existing `/parties/new` journey;
- no EF model change and no new migration.

This package does not implement Party-level lifecycle, Contacts/Addresses, duplicate scoring, Merge, Tax Identity lifecycle or consuming Sales/Purchasing eligibility enforcement.

## Why this slice was selected

Remaining Party candidates were compared against frozen PLAN-003 + PLAN-010 and existing PARTY-IMP-001/002/003 implementation.

Party Role lifecycle was dependency-minimal because:
- Party Role ACTIVE/INACTIVE is already frozen;
- Party Role state is independent from Party state;
- `party.role.manage` explicitly covers activate/deactivate/reactivate;
- `parties.party_roles` already contains state and optimistic version;
- CUSTOMER/SUPPLIER identity and company-scoped Party lookup already exist;
- no new entity/table/column is required;
- no fuzzy duplicate algorithm, provider rule or legal lookup is required;
- no Finance/stock/account/cash/cost authority is introduced.

Deferred candidates remained broader or under-specified:
- soft duplicate review lacks frozen normalization/similarity/score/threshold;
- Contact/Communication/Address adds new authoritative entities and wider UI/migration surface;
- Party reactivation reruns duplicate/legal identity validation and affects all roles;
- Merge requires conflict review over Party children not yet implemented;
- Tax Identity follow-up requires separately frozen sensitive read/lifecycle/provider contracts.

## Repository / tested state

Repository:
- `ufukunal/MarsOtomasyon`

Branch:
- `main`

Starting HEAD:
- `01f21ff4d7046e4d9d42be3ce16bb4688c562afb`

Readiness:
- `docs/plan/03-cariler/p5-fourth-slice-readiness.md`

Readiness commit:
- `62ea9e624311d71027f748b91520fdb6d2f6d487`

Implementation commit:
- `f640fceae01fe734a273c1f3adc4136d497be28d`

Frontend nullability correction / final tested commit:
- `f746139981d79941d414e318ebcab017f8ae4df8`

Foundation Build:
- run `35910331829`
- job `107348248484`
- SUCCESS.

Foundation Test Deploy:
- run `35910331820`
- job `107348248383`
- SUCCESS.

## Frozen lifecycle semantics

- Party remains one company-scoped authoritative identity.
- CUSTOMER and SUPPLIER remain roles of that Party.
- Party Role state is independent from Party state.
- Role identity remains Party + RoleType.
- Role states remain ACTIVE / INACTIVE.
- Deactivating CUSTOMER does not mutate SUPPLIER.
- Deactivating SUPPLIER does not mutate CUSTOMER.
- Deactivation preserves history and does not delete the role row.
- Reactivation updates the same role row; it does not insert a duplicate role.
- Existing PARTY-IMP-002 first activation endpoint remains the authority for first role creation.
- Lifecycle transition requires the caller's expected positive role version.
- Accepted transition increments version.
- Stale version is a concurrency conflict.
- Same-state request is a conflict rather than a silent write.
- Deactivation requires a reason.
- Reactivation does not invent a mandatory reason.
- CompanyId is trusted execution-context data and is never accepted from request JSON.
- `party.role.manage` is required in API and Application.
- No role lifecycle operation posts Finance/Account/Cash/Bank/Stock/Reservation/Cost effects.
- Historical document snapshots are unaffected.

## Physical database contract

No EF model change was required.

Existing table:
- `parties.party_roles`.

Existing fields reused:
- `party_id`;
- `role_type`;
- `state`;
- `version`;
- `created_at`.

Existing DB constraints remain authoritative:
- Party FK with delete RESTRICT;
- unique Party + RoleType;
- CUSTOMER/SUPPLIER valid representation;
- ACTIVE/INACTIVE valid representation;
- version > 0;
- EF optimistic concurrency token on Version.

No new:
- table;
- column;
- index;
- check;
- FK;
- migration.

Final EF pending-model verification:
- PASS;
- “No changes have been made to the model since the last migration.”

Committed migration count:
- 5 before PARTY-IMP-004;
- 5 after PARTY-IMP-004.

No no-op migration was created.

## Application contract

Added:
- `src/Mars.Application/Parties/ChangePartyRoleState/ChangePartyRoleState.cs`.

Command:
- `ChangePartyRoleStateCommand`.

Input:
- Party public UUID;
- role CUSTOMER/SUPPLIER;
- target state ACTIVE/INACTIVE;
- expected version;
- optional reason;
- idempotency key.

Handler validates:
- `party.role.manage`;
- Party UUID;
- supported role;
- supported target state;
- expected version > 0;
- durable bounded Idempotency-Key;
- deactivation reason is present;
- audit reason maximum remains consistent with Foundation audit persistence.

Persistence outcomes:
- Changed;
- PartyNotFound;
- RoleNotFound;
- DuplicateOperation;
- StaleVersion;
- AlreadyInTargetState.

Receipt:
- Party UUID;
- role;
- resulting state;
- resulting version;
- correlation id.

## Persistence / transaction

Added:
- `src/Mars.Infrastructure/Persistence/Parties/EfPartyRoleStatePersistence.cs`.

One PostgreSQL transaction:
1. resolve Party by trusted CompanyId + Party public UUID;
2. resolve existing role by Party + RoleType;
3. compare expected version;
4. reject same-state request;
5. update role state;
6. increment version;
7. insert Foundation idempotency operation;
8. append audit;
9. save;
10. complete idempotency;
11. commit.

Concurrency:
- pre-save expected-version mismatch → stale;
- EF `DbUpdateConcurrencyException` after read → stale;
- both map to Application Concurrency / HTTP 409.

Idempotency:
- scope `parties.role.state:{companyId}`;
- duplicate Foundation idempotency constraint → conflict.

## Audit

Actions:
- `PartyRoleDeactivated.CUSTOMER`
- `PartyRoleDeactivated.SUPPLIER`
- `PartyRoleReactivated.CUSTOMER`
- `PartyRoleReactivated.SUPPLIER`

Audit includes:
- trusted actor;
- company;
- branch when present;
- correlation;
- Party public UUID;
- action;
- deactivation reason.

No Finance payload or Tax Identity data is written.

## Authorization / security

Permission:
- `party.role.manage`.

Enforcement:
- API policy;
- Application handler.

Security boundaries:
- authentication alone is insufficient;
- route UUID is not a company-authority bypass;
- Party lookup uses trusted CompanyId;
- request contains no CompanyId;
- frontend state/buttons are UX only, not authorization;
- no new secret/auth/provider path;
- no Finance permission leakage.

## API

Existing first activation route remains:
- `POST /api/v1/parties/{partyPublicId}/roles`.

New lifecycle route:
- `POST /api/v1/parties/{partyPublicId}/roles/{role}/state`.

Body:
- `state`: ACTIVE or INACTIVE;
- `version`: positive expected version;
- `reason`: required for INACTIVE, optional/null for ACTIVE.

Header:
- `Idempotency-Key`: required.

No CompanyId request field.

Mappings:
- unauthenticated → 401;
- missing `party.role.manage` → 403;
- invalid input → 400;
- Party not found in trusted company → 404;
- role not found → 404;
- duplicate operation → 409;
- stale version → 409;
- already target state → 409;
- success → 200.

## Mars.Web

Existing:
- `/parties/new`.

After first role activation:
- the role's current state/version is held in page state;
- activation control becomes lifecycle control;
- ACTIVE offers deactivation;
- INACTIVE offers reactivation;
- deactivation requires reason in UI;
- lifecycle request sends expected version;
- each mutation uses a fresh idempotency key;
- request contains no CompanyId;
- success updates state/version;
- successful transition clears reason input;
- failures remain explicit and do not erase Party/other role/Tax Identity state.

This does not claim a general existing-Party detail/editor because Party read/detail is not implemented.

## Verification evidence

### Foundation Build — SUCCESS

Run:
- `35910331829`

Job:
- `107348248484`

Evidence:
- frontend tests: 15 / 15 PASS;
- Release build: PASS;
- warnings: 0;
- errors: 0;
- Foundation targeted tests: 50 / 50 PASS;
- missing `party.role.manage` denied before persistence: PASS;
- state/version/deactivation reason validation: PASS;
- trusted-company deactivation audit/idempotency write: PASS;
- reactivation without invented mandatory reason: PASS;
- PartyNotFound/RoleNotFound/DuplicateOperation/StaleVersion/SameState mapping: PASS;
- EF pending-model: PASS — no model changes since last migration;
- API smoke: PASS;
- project references: PASS.

### Foundation Test Deploy — SUCCESS

Run:
- `35910331820`

Job:
- `107348248383`

Pre-deploy:
- frontend tests: 15 / 15 PASS;
- Release build: PASS, 0 warnings / 0 errors;
- Foundation targeted tests: 50 / 50 PASS;
- migration safety: 5 committed migrations PASS;
- EF model drift: PASS;
- remote preflight: PASS;
- TEST migration count before deployment: 5.

Remote deployment:
- API image build: PASS;
- migrator image build: PASS;
- Web image build: PASS;
- database update: PASS with no new PARTY-IMP-004 migration;
- runtime grants: PASS;
- Compose validation: PASS;
- health gate: PASS;
- migration count after deployment: 5;
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
- unauthenticated Party Role activation POST → 401;
- unauthenticated Party Role lifecycle POST → 401;
- unauthenticated Party Tax Identity POST → 401;
- OpenAPI → 200;
- smoke result: PASS.

A real authenticated role lifecycle mutation against TEST is not claimed.

## Failed verification history

### Initial implementation

Commit:
- `f640fceae01fe734a273c1f3adc4136d497be28d`.

Foundation Build:
- run `35910151159`;
- job `107347638198`;
- failed at frontend verification before .NET build.

Foundation Test Deploy:
- run `35910151150`;
- job `107347637567`;
- failed at frontend verification before remote deployment.

Failure:
- TypeScript TS18047 in `party-create.ts`;
- deactivation `reason` remained nullable from TypeScript's perspective at the length check.

Correction:
- `f746139981d79941d414e318ebcab017f8ae4df8`;
- message `fix: narrow Party role deactivation reason`;
- explicit null narrowing only;
- no domain, API, DB or lifecycle contract change.

Final verification on the corrected commit succeeded in both workflows.

No failed run is hidden.

## Explicitly deferred

- soft/fuzzy duplicate candidate/review;
- Contact Person;
- Communication Point;
- Address;
- Party deactivate/reactivate;
- Party Merge;
- Tax Identity read/read_full/edit/deactivate;
- Tax Identity verification/provider/non-TR schemes;
- External Mapping;
- Sales role-eligibility enforcement;
- Purchasing role-eligibility enforcement;
- Finance integration.

## Full Test Day pending

- concurrent deactivate/deactivate;
- deactivate/reactivate race;
- first activation vs deactivation race;
- role lifecycle vs consuming document creation;
- cross-company IDOR;
- broad permission matrix;
- authenticated browser create → activate → deactivate → reactivate;
- CUSTOMER inactive while SUPPLIER remains active in consuming modules;
- Finance no-ledger-effect proof;
- stale multi-client role edit;
- performance/load/security regression;
- backup/restore.

## Completion decision

PARTY-IMP-004 is COMPLETED.

Completion evidence exists for:
- source-backed dependency-minimal scope;
- implementation;
- expected-version lifecycle concurrency;
- authorization/company/audit/idempotency contracts;
- frontend lifecycle behavior;
- no-EF-model-change proof;
- Release build/targeted tests;
- remote TEST deployment;
- security boundary and OpenAPI smoke;
- migration count remaining 5.

## Planning progress

Unchanged:
- Master Section-8 planning coverage: 9 / 30 = 30.0%;
- P2 core commercial planning: 8 / 8 = 100.0%.

These remain planning-coverage metrics, not implementation-progress metrics.

## Next safe action

Remaining Parties scope includes:
- soft duplicate candidate/review;
- Contact/Communication/Address;
- Party lifecycle;
- Merge;
- Tax Identity follow-up;
- External Mapping.

Repository does not freeze their next implementation order or a PARTY-IMP-005 scope.

Next task:
- define the next smallest coherent Parties vertical slice from frozen PLAN-003 + PLAN-010;
- do not assign PARTY-IMP-005 until exact scope is source-backed and frozen.
