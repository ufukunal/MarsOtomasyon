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

## FW-IMP-004 evidence
Implementation:
- persistence-neutral audit, idempotency and outbox contracts exist in Mars.Application
- EF Core persistence records/mappings/stores are owned by Mars.Infrastructure
- committed Foundation schema contains only:
  - foundation.audit_events
  - foundation.idempotency_operations
  - foundation.outbox_messages
- durable idempotency uniqueness is company-neutral logical scope + operation key as defined by the Foundation contract
- outbox event identity is unique and delivery state is durable in PostgreSQL
- minimum bounded/cancellation-aware outbox batch processor exists in Mars.Worker
- no ERP domain schema, auth provider, OpenAPI tooling, UI or deployment was introduced
- no real PostgreSQL migration was applied in this package

Migration:
- src/Mars.Infrastructure/Persistence/Migrations/Foundation/20260922095311_FwImp004FoundationPrimitives.cs
- generated through EF Core tooling, not hand-authored as parallel DDL
- committed model snapshot has no pending model changes

Verification:
- tested commit: `06109051f530dff60774dc43d369986b343f4158`
- GitHub Actions run: `35713295141`
- .NET SDK: `10.0.401`
- runtime: `10.0.12`
- restore: PASS
- Release build: PASS — 0 warnings, 0 errors
- targeted Foundation tests: PASS — 19 / 19
- committed migration scope/model-drift verification: PASS
- project-reference verification: PASS

Failed verification history is preserved in canonical FW-IMP-004 evidence.

## Current phase
P4 — Foundation implementation
Status: IN PROGRESS

## Accepted FW-IMP-005 technology decisions
- Authentication/identity: ASP.NET Core Identity (.NET 10) + OpenIddict 7.7.1 stable
  - ADR: `docs/plan/decisions/ADR-0003-identity-openiddict-baseline.md`
- OpenAPI: Microsoft.AspNetCore.OpenApi 10.0.12
  - ADR: `docs/plan/decisions/ADR-0004-aspnet-openapi-baseline.md`

Portability rule:
- ERP authorization/company/branch semantics remain Mars-owned, not provider-owned.
- client auth uses standard OAuth/OIDC boundaries where applicable.
- OpenAPI contract semantics remain Mars-owned and are not coupled to Swagger UI/client generator tooling.

## Next repository-defined package
`FW-IMP-005 — API foundation implementation`

Status: READY.

Do not pull unrelated deferred Foundation gates forward.

## Planning progress
- Master section-8 sequence: 9 / 30 = 30.0%
- P2 core commercial: 8 / 8 = 100.0%

Implementation completion does not increment section-8 planning completion.

## Full Test Day
Still deferred by policy.
