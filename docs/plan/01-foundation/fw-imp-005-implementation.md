# FW-IMP-005 — API Foundation Implementation

Status: COMPLETED
Date: 2026-09-22

## Scope implemented

FW-IMP-005 implemented only the repository-defined API Foundation slice:

- ASP.NET Core API host/runtime behavior on .NET 10;
- middleware/pipeline baseline;
- ASP.NET Core Identity + OpenIddict authentication integration;
- trusted authenticated-principal adaptation into the existing Mars execution context;
- server-side authorization integration hooks without ERP permission invention;
- deterministic Foundation result/error to HTTP mapping;
- Microsoft.AspNetCore.OpenApi document generation;
- `/api/v1` routing/version-contract proof surface;
- separate liveness/readiness endpoints;
- targeted API/auth/OpenAPI/health/model verification.

No Sales, Party, Product, Purchasing, Warehouse, Finance or other ERP business endpoint/rule was introduced.

## Authentication and identity

Accepted ADR:
- `docs/plan/decisions/ADR-0003-identity-openiddict-baseline.md`

Implemented separation of concerns:

1. ASP.NET Core Identity owns account/credential identity primitives.
2. OpenIddict owns OAuth 2.0 / OpenID Connect protocol/token authority.
3. Incoming API requests are authenticated through a Mars composite authentication boundary.
4. Authenticated identity is adapted into Mars-owned `IExecutionContext`.
5. ERP permission/company/branch authorization remains Mars-owned and is not delegated to Identity/OpenIddict.

Identity implementation:
- minimal `MarsIdentityUser : IdentityUser<Guid>`;
- UUID user key;
- EF Core-backed Identity persistence through `MarsDbContext`;
- unique email required;
- no business-profile fields were guessed.

API authentication boundary:
- bearer requests use OpenIddict validation;
- non-bearer requests use the ASP.NET Core Identity application cookie scheme;
- cookie is HttpOnly;
- cookie secure policy is Always;
- SameSite is Lax;
- API login/access-denied redirects are converted to deterministic HTTP 401/403 responses.

OpenIddict server baseline:
- authorization endpoint: `/connect/authorize`;
- token endpoint: `/connect/token`;
- authorization-code flow enabled;
- PKCE required;
- password grant not enabled;
- implicit flow not enabled;
- no custom token format or custom cryptography;
- local validation integration is enabled.

Development/test signing and encryption use ephemeral OpenIddict keys. Production startup deliberately fails closed until production signing/encryption credentials are supplied outside repository source. FW-IMP-005 does not select the production secret store.

No Keycloak or Microsoft Entra dependency was introduced.

## Trusted Mars execution context

Existing contract retained:
- ActorId;
- CompanyId;
- optional BranchId;
- CorrelationId.

Adapter:
- `TrustedExecutionContextFactory` accepts an authenticated `ClaimsPrincipal`;
- ActorId comes from a single trusted OIDC `sub` claim, with `NameIdentifier` compatibility fallback;
- CompanyId requires exactly one `urn:mars:company_id` claim;
- BranchId accepts at most one optional `urn:mars:branch_id` claim;
- identities/scopes must be non-empty UUIDs;
- duplicate/ambiguous trusted scope claims are rejected;
- client request headers such as `X-Company-ID` do not override the authenticated context.

`Mars.Application` and `Mars.Domain` do not depend on OpenIddict persistence types and do not receive `ClaimsPrincipal` as their normal execution-context contract.

## Authorization hooks

Implemented:
- ASP.NET Core authorization services;
- protected Foundation proof endpoint;
- authenticated execution-context establishment before authorization-dependent endpoint execution.

Not implemented:
- ERP permission catalog;
- module-specific permission rules;
- company/branch membership policy;
- warehouse scope;
- role/business authorization definitions.

Those remain later Mars-owned application/domain concerns.

## Correlation and middleware

Pipeline order:
1. correlation id establishment;
2. safe exception boundary;
3. authentication;
4. trusted execution-context adaptation;
5. authorization.

Correlation behavior:
- accepted `X-Correlation-ID` values must be non-empty and no longer than 128 characters;
- otherwise a server value is generated;
- the effective correlation id is returned in the response header.

## Deterministic HTTP error mapping

Existing Foundation categories map as follows:

| Foundation category | HTTP status |
| --- | ---: |
| Validation | 400 |
| Authentication | 401 |
| Authorization | 403 |
| NotFound | 404 |
| Conflict | 409 |
| Concurrency | 409 |
| BusinessRule | 422 |
| RateLimit | 429 |
| Infrastructure | 503 |
| ProviderFailure | 503 |

Unexpected exceptions return:
- HTTP 500;
- code `infrastructure.unhandled`;
- safe generic message;
- correlation id.

Stack traces, SQL/provider internals, credentials, secrets and token material are not returned to the client.

## OpenAPI

Accepted ADR:
- `docs/plan/decisions/ADR-0004-aspnet-openapi-baseline.md`

Implemented:
- `Microsoft.AspNetCore.OpenApi 10.0.12`;
- document name `v1`;
- runtime document endpoint `/openapi/v1.json`;
- `/api/v1/foundation/context` appears in the generated document.

Not introduced:
- Swagger UI;
- Swashbuckle;
- NSwag;
- client-generator technology.

Route/DTO/error semantics remain Mars-owned. The generated OpenAPI document is descriptive output, not domain authority.

## API proof surface

Protected Foundation-only endpoint:
- `GET /api/v1/foundation/context`

Purpose:
- prove authenticated trusted-context adaptation and `/api/v1` convention;
- expose no ERP business semantics.

It returns only the established Mars execution-context identities:
- actorId;
- companyId;
- optional branchId;
- correlationId.

## Health

Liveness:
- `GET /health/live`
- process/pipeline liveness only;
- does not require external dependency checks.

Readiness:
- `GET /health/ready`
- includes PostgreSQL reachability as the current critical internal dependency.

Optional external providers are not part of readiness and no observability-stack decision was pulled into this package.

## Identity/protocol physical structures

Schema:
- `identity`

ASP.NET Core Identity:
- `identity.users`
- `identity.user_claims`
- `identity.user_logins`
- `identity.user_tokens`

OpenIddict:
- `identity.OpenIddictApplications`
- `identity.OpenIddictAuthorizations`
- `identity.OpenIddictScopes`
- `identity.OpenIddictTokens`

The model also retains the existing FW-IMP-004 `foundation` structures.

No ERP domain table was introduced.

## Migration

Committed migration:
- `src/Mars.Infrastructure/Persistence/Migrations/Identity/20260922113226_FwImp005IdentityProtocol.cs`
- generated Designer file;
- shared `MarsDbContextModelSnapshot.cs` updated.

Migration ownership:
- Foundation / Identity protocol infrastructure.

The migration was generated by EF Core tooling; no parallel manual DDL path was introduced.

The migration creates only Identity/OpenIddict Foundation structures in schema `identity`.

No Party, Product, Variant, Reservation, Inventory, Sales, Purchasing, Warehouse, Finance, Returns, Quality or other ERP domain table is created.

No FW-IMP-005 migration was applied to production by this package.

## Packages and exact versions

Added by FW-IMP-005:

`src/Mars.Api`
- `Microsoft.AspNetCore.OpenApi 10.0.12`
- `OpenIddict.AspNetCore 7.7.1`

`src/Mars.Infrastructure`
- `Microsoft.AspNetCore.Identity.EntityFrameworkCore 10.0.12`
- `OpenIddict.EntityFrameworkCore 7.7.1`

Existing persistence baseline retained:
- `Microsoft.EntityFrameworkCore 10.0.12`
- `Microsoft.EntityFrameworkCore.Relational 10.0.12`
- `Microsoft.EntityFrameworkCore.Design 10.0.12`
- `Npgsql.EntityFrameworkCore.PostgreSQL 10.0.3`
- `dotnet-ef 10.0.12`

No unrelated package was added.

## Targeted verification

Implementation starting repository HEAD:
- `d4ee20c6e7cbbcbff1d51f5c886aa5af4026879e`

Final tested implementation commit:
- `22f31b087f887270bb43f4a39038d7c9a9e07b87`

Final successful workflow:
- Foundation Build
- GitHub Actions run `35722169157`
- run number 26

Environment:
- self-hosted Linux runner;
- .NET SDK `10.0.401`;
- host/runtime `10.0.12`;
- Microsoft.AspNetCore.App `10.0.12`;
- Microsoft.NETCore.App `10.0.12`.

Final results:
- tool restore: PASS;
- solution restore: PASS;
- Release build: PASS — 0 warnings, 0 errors;
- targeted Foundation tests: PASS — 26 / 26;
- committed Identity/OpenIddict migration scope check: PASS;
- EF model-drift check: PASS — no pending model changes;
- API liveness smoke: PASS;
- readiness behavior with intentionally unreachable PostgreSQL: PASS — HTTP 503;
- unauthenticated protected `/api/v1/foundation/context`: PASS — HTTP 401;
- OpenAPI generation: PASS;
- generated OpenAPI contains the Foundation `/api/v1` proof route: PASS;
- Swagger UI absence: PASS — `/swagger` HTTP 404;
- project-reference verification: PASS.

Targeted FW-IMP-005 tests verify:
- authenticated principal maps into Mars execution context;
- ambiguous company scope is rejected;
- client request scope headers cannot silently override trusted context;
- deterministic Foundation error-to-HTTP mapping;
- unexpected exception response hides internal details;
- Identity/OpenIddict model remains inside Foundation-owned schemas;
- Identity user key is UUID.

These checks are targeted Foundation evidence. They do not claim full authentication/authorization security coverage.

## Failed verification history

Intermediate failures are retained as implementation evidence rather than hidden.

### Run 35720782612
Build failed because the harness referenced `FwImp005Tests` before that source file was committed. The missing targeted-test source was added.

### Runs 35720869514 and 35720978323
Build failed on an invalid `IdentityBuilder.AddSignInManager` call for the selected minimal Identity registration. The registration was reduced to the required `AddIdentityCore` + EF store baseline.

### Run 35721058820
A pre-existing FW-IMP-004 model assertion incorrectly required the model to contain only the three FW-IMP-004 entities. Identity/OpenIddict legitimately expanded the Foundation model. The assertion was corrected to verify that the FW-IMP-004 structures remain present.

### Runs 35721152860 and 35721395608
Restore, build, targeted tests and generated migration succeeded, then the FW-IMP-005 API smoke failed during startup/request verification. Diagnostics were narrowed without exposing secrets.

### Run 35721559085
Build failed because the targeted exception-middleware test had not supplied the newly required logger dependency. The test was corrected with a null logger.

### Run 35721621162
API startup failed with OpenIddict reporting that no OAuth/OIDC flow was enabled. The server baseline was corrected to authorization-code flow with required PKCE; password and implicit flows were not introduced.

### Run 35721791029
Restore, build, targeted tests and migration generation passed; API smoke still failed. Cookie authentication challenge/access-denied behavior was then corrected to return API status codes rather than redirects.

### Run 35721899141
First full FW-IMP-005 pipeline success after the cookie challenge correction.

### Runs 35721981397 and 35722169157
Final model snapshot/migration capture and committed migration verification both passed. Run `35722169157` is the final implementation verification evidence.

## Infrastructure-change resilience

Preserved:
- `Mars.Application` and `Mars.Domain` have no OpenIddict persistence dependency;
- authenticated-principal adaptation is isolated at the API boundary;
- ERP permission/company/branch semantics remain Mars-owned;
- standard OAuth/OIDC semantics are preferred over provider-specific client behavior;
- Identity/OpenIddict persistence is Infrastructure-owned;
- route/error/context semantics remain Mars-owned rather than OpenAPI-tool-owned;
- changing the OpenAPI generator later does not require changing `/api/v1` semantics;
- production signing/encryption secret storage remains a separate unresolved infrastructure decision;
- no Desktop/Mobile shell technology was selected.

## Decisions preserved

- ADR-0001 — .NET 10 LTS;
- ADR-0002 — EF Core 10 + Npgsql;
- ADR-0003 — ASP.NET Core Identity + OpenIddict 7.7.1;
- ADR-0004 — Microsoft.AspNetCore.OpenApi 10.0.12.

## UNKNOWN / deferred, not FW-IMP-005 blockers

- production secret-store technology;
- production OpenIddict signing/encryption credential mechanism;
- concrete OAuth/OIDC client registrations and redirect URIs;
- account/login UX;
- ERP permission definitions and permission matrix;
- structured logging/metrics stack;
- object/file storage backend;
- optional scheduling library;
- Desktop shell technology;
- Mobile shell technology;
- production reverse proxy/tunnel details.

FW-IMP-005 is not blocked by these deferred decisions.

## Full Test Day pending

Still deferred by policy:
- full authentication/authorization matrix;
- token/session/revocation security regression;
- real browser/native authentication E2E;
- full PostgreSQL integration;
- concurrency/load;
- backup/restore;
- provider matrix;
- broad security regression;
- performance/load;
- cross-platform regression.

## Planning metric

Exact master section-8 planning completion remains:
- 9 / 30 = 30.0%.

P2 remains:
- 8 / 8 = 100.0%.

FW-IMP-005 is implementation progress and does not increment section-8 planning completion.

## Next package

Repository-defined next Foundation package:
- `FW-IMP-006 — Mars.Web + Mars.UI foundation`

FW-IMP-006 may be marked READY after state/handoff closure.

Its planned scope is:
- Vite/TypeScript;
- web shell/router/API client;
- design tokens;
- Button/Field/Dialog/Tabs/Lookup/Grid baseline.

No FW-IMP-006-specific unresolved owner technology gate is currently identified in the Foundation decision-gate list.

Do not silently select Desktop/Mobile shell technology; those remain later P10 decisions.

Do not pull production deployment decisions into FW-IMP-006.
