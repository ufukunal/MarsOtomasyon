# P5 Parties — First Vertical Slice Readiness

Status: SCOPE DEFINITION COMPLETED / IMPLEMENTATION BLOCKED
Date: 2026-09-23
Phase: P5 — Core application implementation
Module: Parties
Dedicated implementation work-package ID: NOT ASSIGNED

## Objective

Define the smallest coherent first Parties implementation vertical slice from frozen PLAN-003 and PLAN-010 contracts before any Party schema/code mutation.

The repository-defined P5 dependency order starts with Parties.

## Starting repository state

Repository:
- `ufukunal/MarsOtomasyon`

Branch:
- `main`

Verified starting HEAD:
- `55f183d902c74759ca7dd56f16140ad7f7496cd3`

P4 Foundation implementation:
- COMPLETED
- FW-IMP-001 through FW-IMP-008 completed

Planning metric remains:
- master section-8 sequence: 9 / 30 = 30.0%
- P2 core commercial: 8 / 8 = 100.0%

## Active roles

Primary:
- erp-domain-specialist — Party meaning, lifecycle, create-flow invariants, role/duplicate/snapshot boundaries.
- database-architect — minimum normalized Party physical model and constraint/concurrency requirements.
- software-architect — first vertical-slice boundary, dependency direction and transaction ownership.

Reviewers:
- software-developer — smallest implementable code shape and existing Foundation reuse.
- security-specialist — server-side `party.create`, company isolation and client trust boundary.
- software-test-engineer — targeted proof and Full Test Day deferrals.
- ux-ui-specialist — minimum create-screen flow and duplicate-warning behavior.
- web-design-specialist — Mars.Web/Mars.UI reuse and accessible request/error states.
- accounting-finance-specialist — verifies Party create has no Account/Cash/Bank/stock/value authority.

## Sources checked

Governance/state:
- `docs/plan/ai-cmd.md`
- `docs/ai/README.md`
- `docs/ai/autocomplete.md`
- `docs/ai/skill-router.md`
- `docs/ai/session-execution-protocol.md`
- `docs/plan/master-project-plan.md`
- `docs/plan/planning-standard.md`
- `docs/plan/project-state.yaml`
- `docs/plan/active-task.yaml`
- `docs/plan/handoff/current.md`
- `docs/plan/tasks/active.md`
- `docs/plan/tasks/completed.md`
- `docs/plan/tasks/backlog.md`

Frozen Party:
- `docs/plan/03-cariler/README.md`
- `docs/plan/03-cariler/plan.md`
- `docs/plan/03-cariler/workflows.md`
- `docs/plan/03-cariler/forms.md`
- `docs/plan/03-cariler/data-contract.md`
- `docs/plan/03-cariler/permissions.md`
- `docs/plan/03-cariler/integrations.md`
- `docs/plan/03-cariler/reports.md`
- `docs/plan/03-cariler/acceptance-criteria.md`
- `docs/plan/03-cariler/full-test-day.md`

Frozen logical DB:
- `docs/db/00-domain-dictionary.md`
- `docs/db/01-design-principles.md`
- `docs/db/02-module-ownership.md`
- `docs/db/03-entity-catalog.md`
- `docs/db/04-relationships.md`
- `docs/db/05-ledgers-and-finance.md`
- `docs/db/06-snapshots-and-projections.md`
- `docs/db/07-constraints-and-concurrency.md`
- `docs/db/08-index-access-patterns.md`
- `docs/db/09-migration-conventions.md`
- `docs/db/acceptance-criteria.md`
- `docs/db/full-test-day.md`

Foundation/current source:
- `docs/plan/01-foundation/framework-plan.md`
- `docs/plan/01-foundation/fw-imp-003-implementation.md`
- `docs/plan/01-foundation/fw-imp-004-implementation.md`
- `docs/plan/01-foundation/fw-imp-005-implementation.md`
- `docs/plan/01-foundation/fw-imp-006-implementation.md`
- `docs/plan/01-foundation/fw-imp-007-implementation.md`
- `docs/plan/01-foundation/fw-imp-008-implementation.md`
- current `src/Mars.Domain`, `src/Mars.Application`, `src/Mars.Infrastructure`, `src/Mars.Api`, `src/Mars.Web`
- current Foundation targeted-test harness
- current TEST deployment baseline

## Selected first-slice candidate

### Create Party Core Identity

Purpose:
- create one company-scoped external subject master before commercial roles are assigned.

This is the smallest real Party state-changing use case because the frozen Create Party workflow explicitly creates Party before CUSTOMER/SUPPLIER role activation, and role activation is optional after Party creation.

The slice deliberately excludes:
- Party Role;
- Tax Identity;
- Contact Person / Communication Point;
- Address;
- Party External Mapping;
- Party Merge Lineage;
- Finance projections;
- Sales/Purchasing consumption.

## Why it is dependency-minimal

Required authoritative entity count:
- one: Party.

No dependency is required on:
- Product;
- Inventory;
- Sales;
- Purchasing;
- Warehouse;
- Finance ledger;
- Checks/Notes;
- Returns.

The action has:
- master create effect only;
- no Reservation effect;
- no Stock effect;
- no Account effect;
- no Cash/Bank effect;
- no Cost/value effect.

Audit is required.

PartyCreated outbox is optional by the frozen workflow and therefore is not required for this smallest slice unless an accepted consumer is introduced.

## Frozen invariants that apply

- Party is one company-scoped authoritative master identity.
- kind is PERSON or ORGANIZATION.
- Party Code is company-scoped and role-neutral.
- initial Party is not a separate Customer or Supplier master.
- role activation is not part of the core identity create transaction unless explicitly added later.
- Party has no authoritative current balance, credit/risk or settlement state.
- create has no stock/account/cash-bank/cost posting effect.
- deterministic duplicate collisions must not create a second accepted authoritative Party.
- fuzzy duplicate matching is warning/review behavior and never automatic merge.
- every mutation uses trusted server-side company scope.
- `party.create` is required.
- create is audited.
- retry/duplicate submission must not create duplicate authoritative effects when idempotency is applied.

## Candidate physical DB contract

The following is the minimum physical intent supported by frozen contracts.

Owning module:
- Parties.

Candidate physical schema:
- `parties` is an implementation-time engineering candidate, not a frozen business rule.

Party row:
- internal identity: BIGINT-style surrogate.
- public identity: UUID.
- company scope: UUID from trusted execution context.
- Party Code: required business identity.
- kind: PERSON / ORGANIZATION.
- legal name: required live legal identity.
- display/trade name: optional live display identity.
- state: ACTIVE at create; future INACTIVE/MERGED transitions are later slices.
- optimistic version/concurrency token.

Required relational guarantees:
- primary key on internal identity.
- unique public UUID.
- unique Party Code in company scope.
- NOT NULL on company/public id/code/kind/legal name/state/version.
- check kind in PERSON / ORGANIZATION.
- check state in ACTIVE / INACTIVE / MERGED so later lifecycle remains compatible.
- stale-write support through optimistic version.

Indexes:
- company + Party Code uniqueness supplies exact Party Code lookup.
- no fuzzy-name/search index is selected until the duplicate/search query shape is defined.

Not in this first physical slice:
- roles;
- tax identities;
- contacts;
- addresses;
- external mappings;
- merge lineage;
- projections.

Company FK:
- logical Company 1→N Party exists, but the repository currently has no physical Company table. The first Party migration must not invent a Foundation Company table merely to satisfy a FK. Whether company authority remains identity/context-only or receives a physical Company master is an unresolved physical-model dependency.

Data-type limits:
- no arbitrary business max lengths are selected from convention.
- exact string limits/normalization must be supported by an accepted source before constrainting business values.

Migration characteristics if eventually approved:
- additive new Parties-owned structure;
- no backfill;
- no destructive operation;
- no existing Party data migration;
- test-server rehearsal required;
- rollback may remove only the new empty/unreferenced structure before business data use; after authoritative data exists, prefer forward-fix.

## Candidate Application contract

State-changing command candidate:
- CreateParty.

Input candidate:
- Party Code, once assignment source is resolved;
- kind;
- legal name;
- optional display name;
- idempotency key.

Trusted, non-client authority:
- ActorId;
- CompanyId;
- optional BranchId is carried by Foundation context but is not Party identity scope;
- CorrelationId.

Result:
- Party public UUID;
- Party Code;
- kind;
- legal/display identity;
- ACTIVE state;
- version/correlation where public contract requires it.

Error categories:
- Validation for invalid shape;
- Authorization for missing `party.create`;
- Conflict for company-scoped Party Code collision or deterministic duplicate conflict;
- Concurrency for stale writes in later edit slices.

## Candidate API contract

Candidate route:
- `POST /api/v1/parties`

Required:
- authenticated request;
- server-side `party.create`;
- trusted CompanyId from Mars execution context;
- no client-authoritative company/branch;
- public UUID response;
- deterministic error mapping;
- `Idempotency-Key` for retry-safe create.

No list/edit/role/address/tax/merge API is included in the first slice.

## Candidate Mars.Web flow

Minimum route candidate:
- `/parties/new`

Identity fields only:
- Party Code, after code-assignment behavior is resolved;
- kind PERSON / ORGANIZATION;
- legal name;
- optional display/trade name.

The screen must:
- use Mars.UI primitives;
- preserve keyboard/label/focus behavior;
- show company context as trusted/non-editable context if surfaced;
- show server validation/conflict without discarding input;
- not expose balance/risk;
- not create Customer/Supplier-specific duplicate forms.

No role/tax/contact/address sections are required for the first implementation slice.

## Audit / outbox / idempotency

Audit:
- REQUIRED for Create Party.
- actor/company/correlation/action/Party public identity/time are recorded.
- no sensitive child PII exists in this first slice.

Outbox:
- PartyCreated is frozen as an optional candidate.
- because no accepted consumer is required by the first slice, outbox emission is deferred rather than invented.

Idempotency:
- candidate requirement for retry-safe POST.
- existing PostgreSQL Foundation idempotency primitive can be reused.
- exact operation scope should include Party create + trusted company.
- duplicate HTTP retry must not create a second Party.

## Targeted verification required if implementation becomes unblocked

Fast only:
- Release build;
- Party domain/application create tests;
- invalid kind/name/code validation;
- company-scoped Party Code duplicate conflict;
- same Party Code allowed or denied across companies according to company-scoped uniqueness — expected: separate companies may own their own Party records;
- trusted company context cannot be overridden by request input;
- `party.create` authorization negative/positive tests;
- duplicate Idempotency-Key behavior;
- audit creation;
- EF model constraint inspection;
- migration safety/model-drift;
- API unauthenticated 401 / unauthorized 403 / authorized create behavior when an accepted permission principal exists;
- Web typecheck/tests/build;
- TEST migration/deploy/readiness/small smoke.

Heavy PostgreSQL concurrency, browser E2E and broad permission matrix remain Full Test Day only.

## Genuine implementation blockers

### BLOCKER 1 — Party permission authority/evaluation is not implemented

Frozen requirement:
- create requires `party.create`;
- authorization is server-side.

Framework contract says Foundation provides:
- permission evaluation interface;
- scope hooks;
- endpoint/command enforcement hooks;
- policy registration convention.

Current implementation provides authentication and trusted Actor/Company/Branch context, but no Mars permission evaluator, grant store, role-permission model, claim convention or policy-registration implementation exists under `src/`.

PLAN-010 logical Foundation entity catalog also does not define permission/grant entities.

Unsafe assumptions that are forbidden:
- inventing a `permission`/role claim type and treating it as authoritative without an accepted issuance/grant contract;
- adding permission tables not present in the frozen logical model;
- treating authenticated == authorized;
- using UI visibility as authorization.

Required resolution:
- explicitly define the Mars-owned permission authority/evaluation contract sufficient for `party.create`, including where grants come from and how an authenticated principal is evaluated.

### BLOCKER 2 — Party Code assignment source is unresolved at runtime

Frozen requirement:
- every Party has one canonical company-scoped, role-neutral Party Code;
- exact format/series belongs Settings/Numbering;
- assignment must be concurrency-safe and immutable after accepted use.

Repository status:
- Settings/Numbering runtime implementation does not exist.
- PLAN-003 does not state that Party Code is manually user-assigned.
- silently accepting user-entered code or silently inventing an auto-number format would create undocumented behavior.

Required resolution:
- choose/plan the initial Party Code assignment contract:
  - explicit manual input under defined validation/immutability rules, or
  - a minimal Settings/Numbering allocation contract before Party create.

Exact formatting can remain deferred, but the authority that assigns the value cannot remain implicit for implementation.

### BLOCKER 3 — Fuzzy duplicate-warning contract is not implementation-ready

Frozen Create Party flow requires:
- deterministic duplicate checks;
- candidate duplicate warnings;
- no automatic merge;
- authorized keep-separate with reason for soft warnings.

Repository defines candidate signal categories but does not define:
- normalization;
- matching algorithm;
- threshold;
- which signal combination produces a warning;
- candidate query contract;
- whether first-slice create may proceed when duplicate-warning infrastructure is unavailable.

Inventing a fuzzy algorithm would be a business/data-quality decision not supported by frozen sources.

Required resolution:
- freeze a minimum duplicate-candidate contract for Create Party, or explicitly approve deferring soft duplicate warnings from the first implementation slice while preserving deterministic hard conflicts.

## SOURCE / INFERENCE / UNKNOWN / BLOCKED

SOURCE:
- Create Party precedes optional role activation.
- Party is company-scoped and PERSON/ORGANIZATION.
- Party Code is company-scoped/role-neutral.
- `party.create` is required.
- audit is required.
- deterministic duplicate collision is conflict.
- fuzzy duplicate is warning only and never auto-merge.
- Party create has no ledger/stock/cash/cost effect.

INFERENCE:
- Create Party Core Identity is the minimum first vertical slice.
- one Party table is sufficient for that slice once blockers are resolved.
- `parties` schema and string/check enum storage are reasonable implementation choices but not frozen business rules.
- outbox can remain absent because PartyCreated is optional and no accepted consumer exists.

UNKNOWN:
- Party Code assignment authority/mechanism for the first runtime implementation.
- fuzzy duplicate matching contract.
- permission grant/evaluation source and transport into authenticated authorization.

BLOCKED:
- schema/code/API/UI implementation of Create Party until the three issues above are resolved or explicitly narrowed by an accepted decision.

## Implementation decision

No Party C#/EF migration/API/TypeScript business implementation is started in this session.

Reason:
- the first slice is identifiable;
- however mandatory server-side permission behavior and two Create Party workflow inputs/guards are not sufficiently defined to implement without inventing policy.

A dedicated implementation work-package ID is intentionally NOT ASSIGNED while the work package is blocked on these decisions. This avoids creating an implementation package whose acceptance contract is still incomplete.

## Full Test Day pending

Existing Party heavy risks remain deferred, especially:
- concurrent tax-identity duplicate creation;
- Party Code allocation concurrency;
- stale edit;
- cross-company IDOR;
- full permission matrix;
- browser create/role/address/lifecycle E2E;
- high-volume duplicate search;
- snapshot persistence with downstream Sales/Purchasing;
- security/privacy audit of sensitive Party data.

## Next safe action

Resolve only the three first-slice blockers:
1. Mars permission authority/evaluation contract for `party.create`;
2. initial Party Code assignment authority;
3. minimum fuzzy duplicate-warning contract or explicit first-slice deferral decision.

After those are frozen:
- assign the first dedicated Parties implementation work-package ID;
- implement Create Party Core Identity;
- keep role/tax/contact/address/merge and other modules out of scope.
