# P5 Parties — Second Vertical Slice Readiness

Status: IMPLEMENTED / COMPLETED
Date: 2026-09-23
Phase: P5 — Core application implementation
Module: Parties
Implementation work package: PARTY-IMP-002
Title: Activate Party Role

## Objective

Freeze and implement the smallest coherent Parties vertical slice after PARTY-IMP-001.

Selected slice:

**Activate Party Role**

The command activates one CUSTOMER or SUPPLIER role on an existing company-scoped Party without creating a second Party identity and without creating any Finance/ledger effect.

## Candidate comparison

### Party Role activation — SELECTED

Why:
- directly frozen by PLAN-003 Create Party and Assign Role workflows;
- one normalized authoritative child entity;
- accepted role values are already frozen: CUSTOMER and SUPPLIER;
- accepted permission already exists: `party.role.manage`;
- no legal/provider dependency;
- no Finance/stock/account/cash/cost posting;
- no prerequisite Tax Identity, Contact or Address entity;
- independently testable through API + current /parties/new journey;
- extends PARTY-IMP-001 without broad Party CRUD.

### Tax Identity — deferred

Requires:
- sensitive-field authorization;
- company + scheme + normalized-value deterministic collision;
- structural VKN/TCKN rules;
- careful separation of local structural validation from current official/provider verification;
- future legal-document readiness boundary.

It is coherent future work but is larger and more legally sensitive than role activation.

### Contacts / Addresses — deferred

Requires multiple new child authorities, lifecycle, purposes/defaults and broader UI surfaces.
It is not the smallest next vertical slice.

### Soft duplicate review — deferred

PARTY-IMP-001 intentionally deferred fuzzy behavior because available evidence was insufficient to freeze an algorithm/threshold.
Role activation does not need fuzzy duplicate behavior.

### Lifecycle / Merge — deferred

Merge is high risk and depends on role/tax/contact/address conflict review.
Deactivate/reactivate Party and role lifecycle remain later dedicated slices.

## Frozen invariants

- one Party may hold CUSTOMER and SUPPLIER simultaneously;
- role activation does not create another Party identity;
- role state is independent from Party state;
- role activation creates no ACCOUNT, CASH/BANK, STOCK, RES or COST effect;
- Party company scope remains authoritative;
- CompanyId comes from trusted execution context and is never accepted from request JSON;
- no cross-company role activation;
- no role-specific balance authority;
- no automatic CUSTOMER/SUPPLIER netting;
- no role-specific Party code;
- no role-specific commercial default is invented;
- Mars-owned PostgreSQL Permission Grant remains authorization authority.

## Physical DB contract

Owning module:
- Parties.

New authoritative entity:
- Party Role.

Physical schema:
- existing `parties`.

Candidate table:
- `parties.party_roles`.

Columns:
- `id`: BIGINT-style surrogate PK;
- `party_id`: required FK to `parties.parties.id`;
- `role_type`: CUSTOMER or SUPPLIER;
- `state`: ACTIVE or INACTIVE representation, initial ACTIVE;
- `version`: positive optimistic concurrency token, initial 1;
- `created_at`: timestamp consistent with existing Party persistence convention.

No Party Role public UUID is required in this slice because the role is addressed by Party public UUID + role type; PLAN-010 explicitly does not require public UUID on every leaf/junction.

Company scope:
- not duplicated onto Party Role;
- inherited from owning Party;
- application lookup resolves Party by trusted CompanyId + Party public UUID before insertion.

Constraints:
- PK on id;
- FK party_id → parties.parties.id;
- delete behavior RESTRICT;
- unique party_id + role_type;
- role_type check/valid representation;
- state check/valid representation;
- version > 0.

Indexes:
- the unique party_id + role_type index is sufficient for this slice's role lookup/duplicate path;
- no speculative additional role index.

Migration:
- additive only;
- create Party Role table/index/FK/checks;
- no backfill;
- no existing Party mutation;
- no destructive operation;
- generated through current EF Core migration path;
- TEST rehearsal required before completion.

## Domain / Application contract

Role types:
- CUSTOMER;
- SUPPLIER.

State:
- activation creates ACTIVE role.

Command:
- ActivatePartyRole.

Input:
- Party public UUID;
- role CUSTOMER or SUPPLIER;
- idempotency key.

Trusted context:
- ActorId;
- CompanyId;
- optional BranchId carried by Foundation but not Party Role identity;
- CorrelationId.

Application checks:
- `party.role.manage`;
- valid role type;
- required valid Party public UUID;
- durable idempotency key;
- Party exists in trusted company;
- duplicate existing role conflicts.

Result:
- Party public UUID;
- role;
- ACTIVE state;
- version;
- correlation id.

Not in this slice:
- deactivate/reactivate role;
- role-specific defaults/references;
- role-specific codes;
- Finance account creation/posting;
- Customer/Supplier duplicate masters.

## Transaction / audit / idempotency / outbox

Transaction:
- Party Role insert;
- Foundation idempotency insert/complete;
- audit append;
- one PostgreSQL transaction.

Audit:
- required;
- actor/company/correlation;
- Party public UUID;
- activated role identified in the audit action;
- no Finance or sensitive-tax payload.

Idempotency:
- required for externally retryable POST;
- scope is company + Party Role activation operation;
- duplicate operation key maps to conflict under the existing Foundation semantics;
- duplicate Party + role maps to deterministic conflict.

Outbox:
- NOT REQUIRED in PARTY-IMP-002;
- `PartyRoleActivated` remains a candidate event only;
- no accepted consumer currently requires it.

## Authorization / company scope

Permission:
- `party.role.manage`.

Enforcement:
- API policy using existing Mars permission requirement;
- handler-level authoritative permission check;
- UI visibility is not authorization.

Company:
- trusted `IExecutionContext.CompanyId`;
- request cannot supply CompanyId;
- Party lookup is scoped by CompanyId + Party public UUID;
- an existing public UUID in another company is treated as unavailable/not found to the caller.

ADR-0005 remains sufficient; no new auth architecture is required.

## API contract

Endpoint:
- `POST /api/v1/parties/{partyPublicId}/roles`.

Headers:
- `Idempotency-Key`: required.

Body:
- `role`: CUSTOMER or SUPPLIER.

No CompanyId field.

Expected mappings:
- unauthenticated → 401;
- authenticated without `party.role.manage` → 403;
- invalid role/idempotency input → 400;
- Party absent from trusted company → 404;
- duplicate Party role → 409;
- duplicate operation key → 409;
- success → 201.

## UI contract

Reuse:
- existing `/parties/new` first-slice form.

After a Party is created:
- retain returned Party public UUID in page state;
- expose separate CUSTOMER and SUPPLIER activation actions;
- each action invokes the role endpoint with a fresh idempotency key;
- success visibly marks that role ACTIVE and disables that activation action;
- 401/403/404/409/error messages remain explicit;
- role activation failure does not erase the successfully created Party.

Not added:
- Party list/detail/search;
- separate Customer master form;
- separate Supplier master form;
- role deactivation UI;
- role defaults;
- Finance balance/risk UI.

## Targeted verification

Normal development:
- .NET Release build;
- targeted Party Role domain/application tests;
- invalid role;
- missing permission denies before persistence;
- trusted CompanyId scope carried to persistence;
- Party-not-found mapping;
- duplicate role conflict;
- duplicate idempotency conflict;
- audit write;
- EF FK/unique/check/concurrency model assertions;
- generated additive migration safety;
- EF pending-model check;
- frontend typecheck/tests/build;
- Web request contract proves no client CompanyId;
- small API/OpenAPI smoke;
- TEST migration/deploy/readiness/smoke.

Remote smoke may prove:
- /parties/new 200;
- unauthenticated role POST 401;
- health/readiness 200;
- OpenAPI route present.

A real authenticated role activation is not required for the normal TEST smoke unless an existing safe test principal/grant path already exists; no auth bypass may be invented.

## Full Test Day pending

- CUSTOMER and SUPPLIER concurrent activation races;
- same role concurrent activation;
- role activation racing future role deactivation;
- role activation racing document creation;
- cross-company IDOR matrix;
- broad permission matrix;
- authenticated browser create → dual-role flow;
- dual-role Sales/Purchasing eligibility integration;
- Finance proof that role activation creates no ledger/balance effect;
- role lifecycle concurrency once deactivation exists;
- performance/load/security regression.

## Decision

The next dependency-minimal Parties implementation work package is:

**PARTY-IMP-002 — Activate Party Role**

Status:
- IMPLEMENTED / COMPLETED.

Canonical completion evidence:
- `docs/plan/03-cariler/party-imp-002-implementation.md`
- Foundation Build run `35889499695` — SUCCESS
- Foundation Test Deploy run `35889499708` — SUCCESS

No Party Tax Identity, Contact, Address, duplicate-review, merge, lifecycle or Finance behavior is included.
