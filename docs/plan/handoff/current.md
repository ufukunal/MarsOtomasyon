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

## FW-IMP-001 evidence
Implementation:
- `Mars.slnx`
- `Directory.Build.props` targeting `net10.0`
- seven minimal .NET projects under `src/`
- project references encode the accepted dependency direction
- `.github/workflows/foundation-build.yml` performs targeted .NET 10 build verification

Intentional omissions:
- Mars.Web is deferred to FW-IMP-006 where TypeScript/Vite/Mars.UI are owned.
- no test project was created because FW-IMP-001 contains no testable behavior; targeted tests begin with implemented primitives.
- no EF Core/Npgsql package, DbContext, SQL or migration was introduced.

Build evidence:
- verified commit: `212022601c177ec6bc8e8438c6188e747cbee2c5`
- GitHub Actions run: `35702737435`
- runner: self-hosted `mars-ci`
- .NET SDK: `10.0.401`
- runtime: `10.0.12`
- restore: PASS
- Release build: PASS — 0 warnings, 0 errors
- project-reference listing: PASS

## Accepted technology decisions
- .NET 10 LTS — ADR-0001
- EF Core 10 + Npgsql — ADR-0002; targeted raw Npgsql/SQL remains exception-only

## Current phase
P4 — Foundation implementation
Status: IN PROGRESS

## Next work package
`FW-IMP-002 — configuration/context/error primitives`

Scope:
- typed configuration/startup validation primitives
- correlation context
- actor/company context interfaces
- result/error contracts
- targeted tests for these primitives

Do not pull persistence/migrations, auth provider, OpenAPI tooling, Mars.Web/Mars.UI or deployment into FW-IMP-002.

## Planning progress
- Master section-8 sequence: 9 / 30 = 30.0%
- P2 core commercial: 8 / 8 = 100.0%

FW-IMP completion is implementation progress and does not increment the section-8 planning metric.

## Full Test Day
Still deferred by policy.
