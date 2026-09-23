# PARTY-IMP-002 — Activate Party Role

Status: COMPLETED
Date: 2026-09-23

## Objective

Implement the dependency-minimal second Parties vertical slice after PARTY-IMP-001:

- activate CUSTOMER or SUPPLIER on one existing company-scoped Party;
- reuse Mars-owned PostgreSQL permission authority with `party.role.manage`;
- persist Party Role as a normalized Parties-owned child entity;
- keep role activation free of Finance/stock/account/cash/cost posting;
- provide protected role activation API;
- extend the existing `/parties/new` journey with post-create role activation;
- write audit + durable idempotency in the same PostgreSQL transaction;
- generate/apply an additive EF migration;
- prove the slice with targeted build/tests and remote TEST deployment/smoke.

This package does not implement full Party lifecycle or full PLAN-003 Create Party parity.

## Repository / tested state

Repository:
- `ufukunal/MarsOtomasyon`

Branch:
- `main`

Scope-definition commit:
- `e3052ecfc67a0831a745354eb94d4ca1f08c5c6f`
- message: `docs: define PARTY-IMP-002 role activation slice`

Implementation commit:
- `41c1015fceb1ff1c6efef3b55d35338fb4c64f88`
- message: `feat: implement PARTY-IMP-002 role activation`

Migration commit:
- `b540589291d9e115175649f29fab4ef37c943ebf`
- message: `db: add PARTY-IMP-002 role migration`

Verification/deployment trigger commit:
- `09e1eed87edf23da4002127337185f13dc3a6366`
- message: `ci: verify PARTY-IMP-002 committed migration`

## Why Party Role activation was dependency-minimal

Repository sources freeze:
- Party as one company-scoped identity;
- CUSTOMER and SUPPLIER as roles, not separate master identities;
- one Party may hold both roles;
- role activation has no Account/Finance posting effect;
- `party.role.manage` as the role-management permission;
- Party 1→N Party Role;
- role state independent from Party state.

Compared with remaining follow-up candidates:
- Tax Identity adds sensitive-data and legal/current-rule boundaries plus deterministic normalized collision requirements;
- contacts/addresses require multiple new child authorities and broader UI/lifecycle;
- soft duplicate review still lacks an accepted fuzzy algorithm/threshold and remains projection/review behavior;
- merge/lifecycle has wider dependency and conflict-review risk.

Role activation therefore extends the already-existing Party identity with one normalized child entity and no cross-module business authority.

## Frozen invariants preserved

- CUSTOMER and SUPPLIER are roles of one Party identity.
- A Party may hold both roles.
- Role activation does not create a second Party.
- Role activation creates no Finance ledger, receivable, payable, cash/bank, stock, reservation or cost effect.
- Party company scope remains authoritative.
- CompanyId is trusted execution-context data and is not accepted from request JSON.
- No cross-company role activation.
- No role-specific balance authority.
- No automatic CUSTOMER/SUPPLIER netting.
- No role-specific Party Code or numbering rule.
- No role-specific commercial defaults are invented.
- ADR-0005 Mars-owned PostgreSQL Permission Grant remains ERP authorization authority.

## Physical DB contract

Owning module:
- Parties.

New authoritative entity:
- Party Role.

Physical table:
- `parties.party_roles`.

Columns:
- `id` BIGINT-style surrogate primary key;
- `party_id` required FK to `parties.parties.id`;
- `role_type` — Customer or Supplier representation;
- `state` — Active/Inactive representation, initially Active;
- `version` — optimistic concurrency token, initial 1;
- `created_at` — existing timestamp convention.

Company scope:
- inherited from owning Party;
- no duplicate CompanyId column on Party Role;
- persistence resolves Party by trusted CompanyId + Party public UUID before insert.

Constraints:
- primary key on id;
- FK `fk_party_roles_party`;
- delete RESTRICT;
- unique `party_id + role_type`;
- role-type check;
- state check;
- version > 0.

Indexes:
- unique Party + RoleType index is the only new role index;
- no speculative secondary index.

No Party Role public UUID is introduced because the slice addresses a role through Party public UUID + role type.

## Migration

Generated EF migration:
- `src/Mars.Infrastructure/Persistence/Migrations/Parties/20260923163146_PartyImp002RoleActivation.cs`
- generated designer committed;
- `MarsDbContextModelSnapshot.cs` updated.

Migration behavior:
- additive;
- creates `parties.party_roles`;
- no backfill;
- no existing Party mutation;
- no destructive Up operation.

Migration generation helper:
- `.github/workflows/party-migration-generate.yml`;
- final mode is manual `workflow_dispatch`;
- `contents: read`;
- artifact generation only;
- no persistent auto-commit/write behavior.

## Domain / Application

Implemented:
- `PartyRoleType.Customer`;
- `PartyRoleType.Supplier`;
- Active initial role state;
- positive optimistic version;
- Party/company identity validation;
- `ActivatePartyRoleCommand`;
- `ActivatePartyRoleHandler`;
- `party.role.manage` permission check before persistence;
- valid role enforcement;
- required idempotency key;
- Party-not-found handling;
- duplicate operation conflict;
- duplicate role conflict.

Application write-set contains:
- Party Role;
- Foundation idempotency operation;
- Foundation audit entry.

No outbox event is written because no accepted `PartyRoleActivated` consumer is required by this slice.

## Persistence / transaction

`EfPartyRoleActivationPersistence`:
- begins one PostgreSQL transaction;
- resolves owning Party by Party public UUID + trusted CompanyId;
- inserts Party Role;
- inserts/completes durable Foundation idempotency state;
- appends audit;
- commits atomically.

Durable database conflict mapping:
- `ux_idempotency_scope_key` → duplicate operation;
- `ux_party_roles_party_role_type` → duplicate role.

Cross-company public UUID lookup returns Party-not-found behavior rather than allowing role attachment.

## Authorization / security

Permission:
- `party.role.manage`.

Enforcement:
- API defense-in-depth policy;
- Application handler authoritative permission check;
- UI state is not authorization.

Authority:
- ADR-0005 Mars-owned PostgreSQL Permission Grant.

Preserved:
- authentication alone is insufficient;
- CompanyId is never client authority;
- no cross-company role mutation;
- no Finance permission/authority leakage;
- no secret/auth bypass added.

## API

Endpoint:
- `POST /api/v1/parties/{partyPublicId}/roles`.

Body:
- `role`: CUSTOMER or SUPPLIER.

Header:
- `Idempotency-Key`: required.

No CompanyId request field.

Frozen result mapping:
- unauthenticated → 401;
- authenticated without `party.role.manage` → 403;
- invalid input → 400;
- Party unavailable in trusted company → 404;
- duplicate role → 409;
- duplicate operation → 409;
- success → 201.

Remote TEST smoke proves the unauthenticated boundary (401); it does not claim a real authenticated role mutation.

## Mars.Web

Existing route reused:
- `/parties/new`.

After successful Party creation:
- returned Party public UUID remains in page state;
- separate CUSTOMER and SUPPLIER activation actions are exposed;
- role call uses Party-scoped API route;
- fresh idempotency key is used;
- no client CompanyId is sent;
- successful role state is shown as ACTIVE and activation action is disabled;
- role failure does not erase the already-created Party.

No separate Customer/Supplier master screen is added.

## Verification evidence

### Foundation Build

Run:
- `35889499695`

Job:
- `107278081364`

Result:
- SUCCESS.

Evidence:
- frontend targeted tests: 13 / 13 PASS;
- Party Web role-activation request contract: PASS;
- no client company authority in role request: PASS;
- static frontend architecture checks: PASS;
- .NET Release build: PASS;
- warnings: 0;
- errors: 0;
- Foundation targeted tests: 40 / 40 PASS;
- Party Role frozen invariants: PASS;
- missing `party.role.manage` denied before persistence: PASS;
- trusted company audit/idempotency write: PASS;
- not-found and duplicate outcome mapping: PASS;
- Party Role FK/uniqueness/concurrency model: PASS;
- EF pending-model check: PASS — no model changes since last migration;
- API smoke: PASS;
- project-reference gate: PASS.

### Foundation Test Deploy

Run:
- `35889499708`

Job:
- `107278082716`

Result:
- SUCCESS.

Pre-deploy:
- frontend tests: 13 / 13 PASS;
- Release build: PASS, 0 errors;
- Foundation targeted tests: 40 / 40 PASS;
- migration safety scan: PASS for 4 committed migrations;
- EF model drift: PASS;
- PostgreSQL master/runtime logins: PASS;
- runtime privilege model: PASS;
- remote TEST preflight: PASS;
- migration count before deploy: 3.

Remote deploy:
- API image build: PASS;
- migrator image build: PASS;
- Web image build: PASS;
- migration `20260923163146_PartyImp002RoleActivation`: APPLIED;
- migration: PASS;
- runtime grants: PASS;
- Compose validation: PASS;
- health gate: PASS;
- migration count after deploy: 4;
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
- OpenAPI → 200;
- smoke result: PASS.

## Failed verification history

No final implementation defect remained in the verified HEAD.

The migration-generation phase used the existing one-shot/manual EF tooling path before the migration was committed. Final verification was performed only after the generated migration and model snapshot were present.

No failed remote TEST deployment is carried as unresolved evidence for PARTY-IMP-002.

## Explicitly deferred

Not implemented:
- role deactivate/reactivate;
- role-specific defaults/references/codes;
- Tax Identity;
- Contact Person / Communication Point;
- Address;
- soft duplicate review;
- External Mapping;
- Merge;
- Finance balance/risk/ledger;
- Sales/Purchasing eligibility integration;
- production deployment.

## Full Test Day pending

- CUSTOMER and SUPPLIER concurrent activation races;
- same-role concurrent activation;
- role activation racing future role deactivation;
- role activation racing document creation;
- cross-company IDOR matrix;
- broad permission matrix;
- authenticated browser create → dual-role flow;
- Sales/Purchasing dual-role eligibility integration;
- Finance proof that role activation creates no ledger/balance effect;
- role lifecycle concurrency once deactivation exists;
- performance/load/security regression;
- backup/restore.

## Completion decision

PARTY-IMP-002 is COMPLETED.

Evidence exists for:
- frozen dependency-minimal scope;
- implementation;
- generated committed additive migration;
- targeted Domain/Application/EF/Web tests;
- build and model-drift verification;
- remote TEST migration;
- readiness/health;
- role endpoint unauthenticated security boundary;
- runner-to-TEST smoke.

A real authenticated role activation against TEST is not claimed.

## Planning progress

Unchanged:
- Master section-8: 9 / 30 = 30.0%;
- P2 core commercial: 8 / 8 = 100.0%.

## Next safe action

Repository follow-up after Party Role activation still includes:
- Tax Identity + deterministic collision;
- accepted soft duplicate candidate/review;
- contacts/addresses;
- role lifecycle after activation;
- later Party lifecycle/merge.

The repository does not yet freeze the next ordering or a PARTY-IMP-003 scope.

Therefore the next safe task is:
- define the next smallest coherent Parties vertical slice from the remaining frozen PLAN-003 + PLAN-010 requirements;
- do not assign PARTY-IMP-003 before exact scope is frozen.
