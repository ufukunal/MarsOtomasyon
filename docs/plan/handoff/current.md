# Current Handoff

## Repository
- Repository: ufukunal/MarsOtomasyon
- Allowed branch: main
- Current HEAD: verify from Git at session start
- Master plan: docs/plan/master-project-plan.md
- Session protocol: docs/ai/session-execution-protocol.md

## Completed
- P2 Core Commercial Workflow Planning: COMPLETED / FROZEN
- P3 Logical Database Model: COMPLETED / FROZEN
- P4 Foundation readiness: COMPLETED
- FW-IMP-001 — repository solution skeleton: COMPLETED
- FW-IMP-002 — configuration/context/error primitives: COMPLETED
- FW-IMP-003 — persistence/migration baseline: COMPLETED
- FW-IMP-004 — audit/idempotency/outbox foundations: COMPLETED
- FW-IMP-005 — API foundation implementation: COMPLETED

## FW-IMP-005 evidence

Canonical report:
- `docs/plan/01-foundation/fw-imp-005-implementation.md`

Implementation:
- ASP.NET Core API host/pipeline runs on .NET 10;
- ASP.NET Core Identity + OpenIddict 7.7.1 implements the accepted identity/protocol boundary;
- Identity/OpenIddict persistence is EF Core/Npgsql-backed in schema `identity`;
- authenticated principal is adapted at the API boundary into Mars `IExecutionContext`;
- client-provided company/branch headers do not override trusted principal scope;
- ERP permission/company/branch semantics remain Mars-owned;
- deterministic Foundation error-to-HTTP mapping exists;
- Microsoft.AspNetCore.OpenApi 10.0.12 produces the v1 document;
- `/api/v1` convention is proven by a Foundation-only protected context endpoint;
- liveness and PostgreSQL-backed readiness are separate;
- Swagger UI, Swashbuckle, NSwag and client-generator technology were not introduced;
- no ERP business endpoint/schema/rule was introduced.

Migration:
- `src/Mars.Infrastructure/Persistence/Migrations/Identity/20260922113226_FwImp005IdentityProtocol.cs`
- EF Core generated;
- creates only Foundation Identity/OpenIddict structures in schema `identity`;
- shared model snapshot has no pending changes;
- no production migration was applied by FW-IMP-005.

Verification:
- final tested implementation commit: `22f31b087f887270bb43f4a39038d7c9a9e07b87`
- GitHub Actions run: `35722169157`
- .NET SDK: `10.0.401`
- runtime: `10.0.12`
- restore: PASS
- Release build: PASS — 0 warnings, 0 errors
- targeted Foundation tests: PASS — 26 / 26
- committed migration scope/model-drift verification: PASS
- API liveness/readiness/protected-route smoke: PASS
- OpenAPI generation and expected `/api/v1` proof route: PASS
- Swagger UI absence: PASS
- project-reference verification: PASS

Failed intermediate verification history and corrections are preserved in the canonical FW-IMP-005 evidence.

## Current phase
P4 — Foundation implementation
Status: IN PROGRESS

## Current package
`FW-IMP-006 — Mars.Web + Mars.UI foundation`

Status: READY.

Planned repository-defined scope:
- Vite/TypeScript;
- web shell/router/API client;
- design tokens;
- Button/Field/Dialog/Tabs/Lookup/Grid baseline.

Decision-gate review:
- no FW-IMP-006-specific unresolved owner gate is currently identified;
- Desktop/Mobile shell technology remains deferred to later P10 work;
- Docker/test deployment and production ingress decisions are not pulled into FW-IMP-006.

## Preserved architecture boundaries
- Mars ERP authorization remains server-side and Mars-owned.
- Web authentication must not establish a localStorage-token baseline.
- `/api/v1` remains the public API version baseline.
- V38 remains a product/UI reference, not production code architecture.
- No ERP business screen/rule should be invented merely to prove the UI framework.

## Planning progress
- Master section-8 sequence: 9 / 30 = 30.0%
- P2 core commercial: 8 / 8 = 100.0%

Implementation completion does not increment section-8 planning completion.

## Full Test Day
Still deferred by policy.
