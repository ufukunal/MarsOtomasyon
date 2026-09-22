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

## FW-IMP-003 evidence
Implementation:
- EF Core 10 / Npgsql persistence baseline is owned by `Mars.Infrastructure`
- `MarsDbContext` exists with no ERP domain entities
- runtime and migration connection configuration are represented by distinct types
- design-time migration tooling uses only `MARS_MIGRATION_CONNECTION_STRING`
- local `dotnet-ef` tool is pinned
- migration location/rules are documented
- no committed domain schema migration was created

Exact package/tool baseline:
- Microsoft.EntityFrameworkCore 10.0.12
- Microsoft.EntityFrameworkCore.Relational 10.0.12
- Microsoft.EntityFrameworkCore.Design 10.0.12
- Npgsql.EntityFrameworkCore.PostgreSQL 10.0.3
- dotnet-ef 10.0.12

Verification:
- tested commit: `63734a34b1c86437504cc304cac1315b3d182ee1`
- GitHub Actions run: `35708370550`
- .NET SDK: `10.0.401`
- runtime: `10.0.12`
- restore: PASS
- Release build: PASS — 0 warnings, 0 errors
- targeted tests: PASS — 11 / 11
- EF migration mechanism probe: PASS; generated probe contained no CreateTable operation and was removed from the CI workspace
- project-reference check: PASS

Failed verification history retained:
- run `35708147597`: build passed but targeted test exposed EF relational patch-version conflict
- run `35708251674`: build + tests passed; migration probe targeted Debug output while only Release was built
- both issues were corrected in scope before the successful run

## Current phase
P4 — Foundation implementation
Status: IN PROGRESS

## Next work package
`FW-IMP-004 — audit/idempotency/outbox foundations`

Do not introduce ERP domain rules or domain module schema in FW-IMP-004.

## Planning progress
- Master section-8 sequence: 9 / 30 = 30.0%
- P2 core commercial: 8 / 8 = 100.0%

Implementation completion does not increment section-8 planning completion.

## Full Test Day
Still deferred by policy.
