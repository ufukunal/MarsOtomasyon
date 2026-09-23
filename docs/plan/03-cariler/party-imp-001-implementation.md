# PARTY-IMP-001 — Create Party Core Identity

Status: COMPLETED
Date: 2026-09-23

## Objective

Implement the first real P5 Parties vertical slice from the frozen PARTY-IMP-001 readiness contract:

- one company-scoped PERSON/ORGANIZATION Party core identity;
- Mars-owned server-side `party.create` permission authority;
- additive PostgreSQL persistence/migration;
- protected `POST /api/v1/parties`;
- Mars.Web `/parties/new`;
- audit + durable idempotency;
- targeted build/test/migration evidence;
- remote TEST deployment/smoke.

This package does not claim full PLAN-003 Create Party parity.

## Repository / tested state

Repository:
- `ufukunal/MarsOtomasyon`

Branch:
- `main`

Original blocker-resolution prompt expected HEAD:
- `f47c6e65cdd0b147c5d8dfebc286931b6bd044cb`

Repository truth had already advanced through blocker resolution and implementation before final verification.

Committed PARTY-IMP-001 migration:
- commit `1c6983bea3f046522183a2a5f366f2b868a721f0`
- migration `src/Mars.Infrastructure/Persistence/Migrations/Parties/20260923155536_PartyImp001CoreIdentity.cs`

Final Foundation Build evidence:
- tested source ancestry includes PARTY-IMP-001 migration and implementation;
- run `35885316247`;
- job `107263853019`;
- result SUCCESS.

Final remote TEST evidence:
- verification/deployment commit `b1305ad0d48b6ef6e5522a9f6f66a46a2926902c`;
- run `35885323206`;
- job `107263878180`;
- result SUCCESS.

## Blocker resolution inherited

### PARTY-BLK-001 — Permission authority

Resolved by:
- `docs/plan/decisions/ADR-0005-mars-erp-permission-authority.md`.

Implemented boundary:
- Mars-owned PostgreSQL Permission Grant authority;
- effective grain ActorId + CompanyId + PermissionCode;
- `party.create` is evaluated server-side;
- Application owns a persistence-neutral evaluator contract;
- Infrastructure owns PostgreSQL evaluation;
- API endpoint applies defense-in-depth policy;
- command handler enforces the permission as well;
- authentication/token claims are not sole ERP permission authority.

### PARTY-BLK-002 — Party Code

Resolved for this slice:
- required caller/API/UI input;
- company-scoped exact stored uniqueness;
- no allocator/series/format invented;
- leading/trailing whitespace rejected;
- no case-folding/locale normalization rule invented.

### PARTY-BLK-003 — Soft duplicate warning

Resolved by explicit first-slice deferral:
- no fuzzy algorithm/threshold/score invented;
- deterministic first-slice conflict authority remains PostgreSQL uniqueness + durable idempotency;
- full PLAN-003 Create Party parity remains future work.

### PARTY-BLK-004 — Company reference

Resolved:
- Party stores trusted required CompanyId UUID;
- CompanyId is derived from Mars execution context;
- request JSON cannot choose company;
- no physical Company FK/table invented in PARTY-IMP-001.

## Implementation

### Domain / Application

Implemented:
- Party core identity aggregate/value semantics;
- PERSON / ORGANIZATION;
- ACTIVE initial state;
- required Party Code;
- required legal name;
- optional display name;
- optimistic version;
- `party.create` permission contract;
- Create Party command/result/orchestration;
- handler-level authorization;
- audit/idempotency transaction contract.

No Party Role, Tax Identity, Contact, Address, Mapping or Merge aggregate was added.

### Foundation permission persistence

Implemented:
- Permission Grant Foundation persistence record/configuration;
- PostgreSQL-backed permission evaluator;
- actor + company + permission grant authority;
- current grant state handling.

This is Mars-owned ERP authorization and remains separate from OpenIddict protocol authority.

### Party persistence

Implemented Parties-owned persistence:
- schema `parties`;
- table `parties.parties`;
- BIGINT-style internal key;
- UUID public id;
- required CompanyId;
- required Party Code;
- kind;
- legal/display name;
- state;
- optimistic version;
- creation metadata.

Key guarantees include:
- unique public id;
- unique CompanyId + PartyCode;
- required/trim checks;
- valid kind/state;
- positive version.

No Company FK is added in this slice.

### Migration

Committed EF Core migration:
- `src/Mars.Infrastructure/Persistence/Migrations/Parties/20260923155536_PartyImp001CoreIdentity.cs`
- generated designer;
- updated `MarsDbContextModelSnapshot.cs`.

Migration is additive:
- creates `parties` schema;
- creates Party persistence;
- creates Foundation Permission Grant persistence;
- no Party data backfill;
- no destructive existing-domain rewrite.

Migration safety verification:
- all three committed migrations passed the Up-operation scan;
- EF reports no pending model changes.

Remote TEST:
- prior migration count: 2;
- PARTY-IMP-001 migration applied successfully;
- resulting migration count: 3.

## API

Implemented:
- protected `POST /api/v1/parties`;
- authentication required;
- server-side `party.create` defense in depth;
- no client CompanyId authority;
- `Idempotency-Key` request contract;
- deterministic result/error mapping;
- endpoint included in current API composition.

Remote TEST unauthenticated POST:
- HTTP 401.

An authenticated live Party creation against TEST is not claimed by this package's remote smoke evidence.

## Mars.Web

Implemented:
- `/parties/new`;
- first-slice Party Code;
- kind;
- legal name;
- optional display name;
- no client CompanyId;
- existing shared API client;
- Mars.UI/Mars.Web conventions.

Remote TEST:
- GET `/parties/new` → 200.

Targeted Web contract confirms first-slice request data only and no client company authority.

## Audit / idempotency / outbox

Audit:
- Create Party writes trusted actor/company/correlation evidence.

Idempotency:
- durable PostgreSQL Foundation idempotency is reused;
- duplicate operation does not create a second accepted Party.

Outbox:
- no PartyCreated outbox event added because the readiness contract explicitly made it non-required without an accepted consumer.

## Verification evidence

### Foundation Build — SUCCESS

Run:
- `35885316247`

Job:
- `107263853019`

Evidence:
- frontend verification: PASS;
- targeted Web tests: 12 / 12 PASS;
- Vite build: PASS;
- .NET Release build: PASS;
- warnings: 0;
- errors: 0;
- Foundation targeted tests: 35 / 35 PASS;
- Party core invariant test: PASS;
- missing `party.create` denied before persistence: PASS;
- trusted company audit/idempotency write: PASS;
- duplicate operation / Party Code conflict mapping: PASS;
- Permission Grant + Party model constraints: PASS;
- committed Party migration count requirement: PASS;
- EF pending-model check: PASS — no model changes since migration;
- API smoke: PASS;
- project-reference gate: PASS.

### Foundation Test Deploy — SUCCESS

Run:
- `35885323206`

Job:
- `107263878180`

Evidence before deployment:
- frontend tests: 12 / 12 PASS;
- .NET Release build: PASS, 0 errors;
- Foundation targeted tests: 35 / 35 PASS;
- migration safety scan: PASS for 3 migrations;
- EF model drift: PASS;
- TEST PostgreSQL master/runtime login: PASS;
- runtime role privilege model: PASS;
- remote preflight: PASS.

Remote deployment:
- API image build: PASS;
- migrator image build: PASS;
- Web image build: PASS;
- migration `20260923155536_PartyImp001CoreIdentity`: APPLIED;
- migration: PASS;
- runtime grants: PASS;
- Compose validation: PASS;
- health gate: PASS;
- EF migration count: 3;
- deploy result: PASS.

Runner-to-TEST smoke:
- GET `/` → 200;
- GET `/components` → 200;
- GET `/proof` → 200;
- GET `/parties/new` → 200;
- GET `/health/live` → 200;
- GET `/health/ready` → 200;
- GET `/api/v1/foundation/context` unauthenticated → 401;
- POST `/api/v1/foundation/proof` unauthenticated → 401;
- POST `/api/v1/parties` unauthenticated → 401;
- GET `/openapi/v1.json` → 200;
- smoke result: PASS.

## Failed verification history

Failure history is retained.

### Foundation Test Deploy run 35882984771

Passed before failure:
- frontend: 12 / 12;
- Release build;
- Foundation tests: 35 / 35;
- existing migration safety.

Failure:
- EF pending-model-change check found Party/Permission model changes without a committed migration.

Correction:
- generate and commit PARTY-IMP-001 migration through EF Core tooling;
- include generated designer and model snapshot.

### Migration helper attempts

Temporary migration-generation helper failures:
- run `35884759101`: workflow syntax/parse issue while attempting one-shot helper;
- run `35884828638`: generated migration/artifact succeeded, helper self-disable Python quoting failed;
- run `35884950856`: generated migration/local commit succeeded, push rejected because GitHub App could not update workflow file without workflow permission.

Correction:
- separate generated migration push from workflow mutation;
- run `35885071041` succeeded and committed the generated migration;
- helper was then retired to manual, read-only artifact generation.

These failures were CI/helper mechanics; generated migration itself was always produced by EF tooling rather than hand-authored parallel DDL.

## Security boundaries preserved

- authenticated does not mean authorized;
- `party.create` remains server-side;
- ERP permission authority remains Mars/PostgreSQL-owned;
- client cannot choose CompanyId;
- no Company table/FK invented;
- no Party balance/risk/ledger authority introduced;
- no soft duplicate heuristic invented;
- no Party Code numbering format invented;
- no production secret/deployment architecture selected;
- runtime PostgreSQL grant path remains restricted/read-only where intended.

## Deferred scope

Not implemented:
- Party Role;
- Tax Identity;
- Contact Person / Communication Point;
- Address;
- External Mapping;
- Merge;
- soft/fuzzy duplicate review;
- Settings/Numbering allocator;
- permission administration UI/role/group model;
- Finance projections;
- Sales/Purchasing Party consumption.

## Full Test Day pending

- concurrent Party Code race against real PostgreSQL;
- future deterministic Tax Identity collision race;
- stale Party edit;
- cross-company IDOR matrix;
- broad permission matrix;
- authenticated browser Party lifecycle E2E;
- high-volume fuzzy duplicate search;
- downstream historical snapshot immutability;
- security/privacy regression;
- performance/load;
- backup/restore.

## Completion decision

PARTY-IMP-001 is COMPLETED.

Completion evidence exists for:
- implementation;
- generated committed migration;
- targeted build/tests;
- permission/domain/persistence invariants;
- remote TEST migration;
- remote readiness;
- Web/API unauthenticated security boundary;
- runner-to-TEST smoke.

The package does not claim full PLAN-003 Party create parity or a real authenticated browser/API Party mutation.

## Planning progress

Unchanged:
- Master section-8: 9 / 30 = 30.0%;
- P2 core commercial: 8 / 8 = 100.0%.

P5 implementation completion does not alter those planning percentages.

## Next safe action

Repository backlog names several Parties follow-up requirements but does not assign their order or a dedicated next implementation work-package ID:
- soft duplicate candidate/review;
- Tax Identity + deterministic collision;
- role activation;
- contacts/addresses;
- later lifecycle/merge.

Therefore the next task is scope definition for the next smallest coherent Parties vertical slice from frozen PLAN-003 + PLAN-010, without inventing a PARTY-IMP-002 ID before the scope is explicit.
