# FW-IMP-008 — Thin Vertical Framework Proof

Status: COMPLETED
Date: 2026-09-23

## Objective

FW-IMP-008 closes the P4 Foundation implementation sequence with the smallest repository-defined vertical framework proof.

The governing Foundation plan requires a non-domain or deliberately minimal feature that proves the request → application → DB → API → UI → audit/outbox pattern without inventing ERP rules.

The selected proof is therefore a Foundation-only authenticated command. It does not represent Party, Product, Inventory, Sales, Purchasing, Warehouse, Finance, Returns or any other ERP business concept.

## Repository state

Repository:
- `ufukunal/MarsOtomasyon`

Branch:
- `main`

Starting HEAD:
- `ae9afe291b20c0be16319a9e3beefca0d3b08d96`

Implementation commit:
- `b8828e2f9cabce0ead7efbdffba9c709331ced8f`

Expanded TEST smoke commit:
- `bf9b3882dbc91ee801d169a62587c7658baaa63b`

Final tested implementation commit:
- `bf9b3882dbc91ee801d169a62587c7658baaa63b`

Verification workflows:
- Foundation Build run `35868005913`, job `107204452548` — SUCCESS on implementation commit `b8828e2f9cabce0ead7efbdffba9c709331ced8f`
- Foundation Test Deploy run `35868151531`, job `107205339908` — SUCCESS on final tested commit `bf9b3882dbc91ee801d169a62587c7658baaa63b`

## Active roles

Primary:
- software-architect — selected the smallest end-to-end Foundation proof and protected transaction/dependency boundaries.
- software-developer — implemented the accepted proof with the existing Foundation abstractions and no new dependency.

Reviewers:
- software-test-engineer — defined targeted handler/UI/build/deployment evidence and preserved Full Test Day boundaries.
- database-architect — verified reuse of existing Foundation persistence structures and no fake/domain schema creation.
- security-specialist — verified trusted authenticated context remains server-owned and no client actor/company authority is introduced.
- system-devops-specialist — verified the existing TEST deployment path accepts the proof and health/readiness remains green.
- erp-domain-specialist — veto boundary: the proof contains no ERP posting, master-data or commercial semantics.

## Scope selected

Technical proof:
- authenticated `POST /api/v1/foundation/proof`;
- request carries only an `Idempotency-Key`;
- actor/company/optional branch/correlation context comes only from the existing trusted authenticated execution context;
- Application creates one Foundation proof write-set;
- Infrastructure persists idempotency, audit and outbox records through one PostgreSQL transaction;
- API returns a technical receipt;
- Mars.Web exposes a Foundation-only `/proof` page using the shared API client;
- TEST deployment verifies Web route availability, API authorization boundary, OpenAPI and health/readiness.

No domain-shaped entity was required.

## Why this is the smallest correct proof

The repository already had:
- trusted execution context;
- deterministic result/error contracts;
- PostgreSQL DbContext;
- durable idempotency;
- audit;
- transactional outbox;
- protected API pipeline;
- OpenAPI;
- Mars.Web router/API client;
- working TEST deployment.

Creating a new demonstration table or generic ERP entity would duplicate existing Foundation capability and create fake domain semantics.

FW-IMP-008 therefore composes the existing primitives instead of introducing a new persistence model.

## Application layer

Created:
- `src/Mars.Application/Foundation/Proof/FoundationProof.cs`

Contracts:
- `FoundationProofCommand`
- `FoundationProofReceipt`
- `FoundationProofWrite`
- `FoundationProofPersistenceOutcome`
- `IFoundationProofPersistence`
- `FoundationProofHandler`

Handler behavior:
- requires a non-empty `Idempotency-Key`;
- caps the operation key at the existing DB field limit of 200 characters;
- creates a company-scoped durable idempotency scope;
- derives actor/company/branch/correlation only from `IExecutionContext`;
- creates an audit record;
- creates a versioned Foundation outbox message;
- returns a deterministic conflict when the company-scoped operation key is already used.

The handler has no EF Core/Npgsql dependency.

## Persistence / transaction

Created:
- `src/Mars.Infrastructure/Persistence/Foundation/EfFoundationProofPersistence.cs`

The implementation composes:
- `MarsDbContext`;
- `IAuditWriter` / `EfAuditWriter`;
- `IIdempotencyStore` / `EfIdempotencyStore`;
- `IOutboxWriter` / `EfOutboxStore`.

Transaction:
1. begin PostgreSQL transaction;
2. stage idempotency operation;
3. stage audit record;
4. stage outbox message;
5. save the three records;
6. mark the idempotency operation succeeded;
7. commit.

Duplicate protection:
- existing unique constraint `ux_idempotency_scope_key` remains the durable authority;
- PostgreSQL unique violation is translated into the Foundation duplicate outcome;
- the transaction is rolled back and the tracked state is cleared.

No second persistence architecture or manual DDL path was introduced.

## Database impact

New schema/table/column/index:
- NONE.

New migration:
- NONE.

Existing structures used:
- `foundation.idempotency_operations`
- `foundation.audit_events`
- `foundation.outbox_messages`

Final model-drift verification:
- no pending model changes.

Committed migration safety scan:
- existing 2 Foundation/Identity migrations passed.

## API

Modified:
- `src/Mars.Api/Program.cs`

Added:
- DI wiring for existing audit/idempotency/outbox EF implementations;
- `IFoundationProofPersistence`;
- `FoundationProofHandler`;
- protected `POST /api/v1/foundation/proof`.

The endpoint:
- requires authentication;
- accepts no actor/company/branch identifiers from the client;
- reads only `Idempotency-Key` from request data;
- uses trusted `IExecutionContext`;
- maps Application errors through the existing deterministic HTTP mapper;
- is included in the generated OpenAPI document.

Unauthenticated TEST request returns HTTP 401.

No ERP permission name was invented.

## Web/UI

Created:
- `src/Mars.Web/src/foundation-proof.ts`

Modified:
- `src/Mars.Web/src/app.ts`
- `src/Mars.Web/src/main.ts`

Added route:
- `/proof`

The page:
- is explicitly a Foundation technical proof surface;
- generates an operation idempotency key;
- uses the existing same-origin `ApiClient`;
- does not create or send actor/company/branch authority;
- reports success correlation/event identity;
- reports authentication requirement on 401;
- reports duplicate operation conflict on 409.

No business screen, ERP form or business rule was introduced.

## Targeted tests

Created:
- `tests/Mars.Foundation.Tests/FwImp008Tests.cs`

Modified:
- `tests/Mars.Foundation.Tests/Program.cs`
- `src/Mars.Web/tests/foundation.test.ts`

Targeted .NET coverage:
- company-scoped idempotency/audit/outbox write-set;
- trusted execution-context propagation;
- missing idempotency key validation;
- oversized idempotency key validation;
- duplicate → Conflict mapping;
- persistence implementation composes the existing Foundation transaction collaborators.

Targeted Web coverage:
- proof page uses the shared API request contract;
- POST path is `/foundation/proof`;
- `Idempotency-Key` is supplied;
- returned correlation is surfaced.

Final counts from TEST deploy run `35868151531`:
- frontend targeted tests: 11 / 11 PASS;
- Foundation targeted tests: 30 / 30 PASS.

## Build / static verification

Final TEST deploy workflow:
- Node frontend verification: PASS;
- Vite `8.3.0` production build: PASS;
- .NET Release build: PASS;
- .NET build errors: 0;
- migration safety scan: PASS;
- EF pending-model-change check: PASS — no changes since last migration;
- remote TEST preflight: PASS.

The Foundation Build workflow on implementation commit also passed:
- frontend verification;
- .NET build;
- targeted Foundation tests;
- migration/model verification;
- API smoke;
- project-reference verification.

## TEST deployment evidence

Final tested commit:
- `bf9b3882dbc91ee801d169a62587c7658baaa63b`

Foundation Test Deploy:
- run `35868151531`
- job `107205339908`
- result: SUCCESS

Remote TEST evidence:
- API image build: PASS;
- migrator image build: PASS;
- Web image build: PASS;
- migration step: PASS;
- runtime grants: PASS;
- Compose validation: PASS;
- deployment health gate: PASS;
- `/proof`: 200;
- `/health/live`: 200;
- `/health/ready`: 200;
- unauthenticated `POST /api/v1/foundation/proof`: 401;
- OpenAPI: 200 and contains the proof endpoint;
- EF migration count remains 2;
- runner-to-TEST smoke: PASS.

Runner-to-TEST final smoke:
- GET `/`: 200
- GET `/components`: 200
- GET `/proof`: 200
- GET `/health/live`: 200
- GET `/health/ready`: 200
- GET `/api/v1/foundation/context`: 401
- POST `/api/v1/foundation/proof`: 401
- GET `/openapi/v1.json`: 200

## Authentication / security boundary

Preserved:
- API proof endpoint requires server-side authentication;
- trusted actor/company/branch context remains derived from the authenticated principal;
- Web does not send actor/company/branch identifiers;
- no permission namespace was invented merely for a technical proof;
- no localStorage/sessionStorage token mechanism was introduced;
- no TEST authentication bypass was introduced;
- no test-only backdoor/header scheme was introduced;
- no production secret decision was made;
- no credential value is stored or reported by FW-IMP-008.

## Authenticated live-path limitation

FW-IMP-008 does not claim that a real authenticated browser session executed the proof mutation against TEST PostgreSQL.

Reason:
- repository currently has no accepted login/account UI or test-user bootstrap flow that can be used without expanding this work package;
- introducing a TEST authentication bypass or synthetic privileged principal endpoint solely to make an E2E proof green would weaken the security boundary and violate the smallest-correct-scope rule.

What is proven instead:
- Application command construction and duplicate/error behavior through targeted tests;
- persistence transaction composition through the accepted infrastructure implementation and build verification;
- API route/OpenAPI/runtime availability on TEST;
- server-side authentication boundary on TEST;
- Web route/shared API contract through targeted UI tests;
- existing PostgreSQL migration/readiness/deployment path remains green.

A real authenticated browser → API → PostgreSQL end-to-end scenario remains a Full Test Day / later authentication-UX test item and is not falsely reported as executed.

## Outbox / Worker boundary

The proof creates a durable outbox message.

It does not claim live external dispatch because:
- current Mars.Worker project remains non-executable;
- FW-IMP-008 does not invent a Worker host or provider integration.

Existing outbox processor targeted tests remain green.

## Failed verification history

No repository verification run failed for FW-IMP-008.

Two successful verification stages were used:
1. implementation commit `b8828e2f9cabce0ead7efbdffba9c709331ced8f` passed Foundation Build and the existing TEST deployment flow;
2. `bf9b3882dbc91ee801d169a62587c7658baaa63b` extended the deployment smoke contract for the new proof surface and passed the final TEST deploy workflow.

No failed CI result is hidden.

## Dependency / architecture impact

Preserved dependency direction:
- Application owns proof orchestration contracts and remains persistence-neutral;
- Infrastructure implements the persistence boundary;
- API composes dependencies and owns transport;
- Web consumes the API;
- Domain receives no Foundation proof dependency;
- no circular project reference;
- no new package/dependency;
- no new DB migration.

The proof is deliberately disposable technical evidence, not a generic ERP entity model.

## Decisions preserved

- ADR-0001 — .NET 10 LTS;
- ADR-0002 — EF Core 10 + Npgsql;
- ADR-0003 — ASP.NET Core Identity + OpenIddict 7.7.1;
- ADR-0004 — Microsoft.AspNetCore.OpenApi 10.0.12;
- PostgreSQL authoritative;
- Valkey non-authoritative;
- Mars-owned ERP authorization semantics;
- no React/Vue/Angular/Bootstrap/Tailwind/jQuery;
- TEST deployment does not define production ingress/secrets/observability;
- heavy suites remain Full Test Day only.

## UNKNOWN / deferred

Not selected by FW-IMP-008:
- production secret store;
- production reverse proxy/tunnel/DNS/TLS;
- structured logging/metrics backend;
- Desktop/Mobile shell technology;
- optional scheduling technology;
- login/account UI;
- runnable Mars.Worker host.

No unresolved item blocks FW-IMP-008 completion.

## Full Test Day pending

Deferred heavy evidence:
- real authenticated browser → API → PostgreSQL vertical-proof E2E;
- full authentication/authorization matrix;
- browser E2E;
- broad PostgreSQL integration/concurrency;
- outbox concurrent claim/retry load;
- runnable Worker/provider delivery E2E when the Worker host exists;
- backup/restore;
- performance/load;
- security regression;
- Desktop/Mobile/cross-platform regression.

## Planning metric

Unchanged:
- Master section-8 sequence: 9 / 30 = 30.0%;
- P2 core commercial: 8 / 8 = 100.0%.

FW-IMP-008 implementation completion does not increment section-8 planning completion.

## P4 exit

FW-IMP-008 completes the repository-defined FW-IMP-001 through FW-IMP-008 Foundation implementation sequence.

P4 exit evidence now exists for:
- successful builds;
- deployed thin Foundation slice on TEST;
- health/readiness;
- migration path;
- audit/idempotency/outbox targeted proof.

P4 — Foundation implementation can therefore be recorded COMPLETED.

## Next phase

The master project plan defines:
- P5 — Core application implementation;
- first dependency/module: Parties.

The repository does not currently define a dedicated implementation work-package ID or exact first Parties vertical-slice scope.

Therefore the next safe action is:
- enter P5;
- define the first Parties implementation work package from the frozen PLAN-003 Party contracts and the frozen logical DB model before mutation;
- do not invent a new work-package ID or start Party schema/code until that scope is repository-defined.
