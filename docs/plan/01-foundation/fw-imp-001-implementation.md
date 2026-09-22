# FW-IMP-001 — Repository Solution Skeleton

Status: COMPLETED
Date: 2026-09-22

## Scope implemented

Created:
- `Mars.slnx`
- `Directory.Build.props`
- `.gitignore`
- `src/Mars.Domain/Mars.Domain.csproj`
- `src/Mars.Contracts/Mars.Contracts.csproj`
- `src/Mars.Application/Mars.Application.csproj`
- `src/Mars.Infrastructure/Mars.Infrastructure.csproj`
- `src/Mars.Api/Mars.Api.csproj`
- `src/Mars.Worker/Mars.Worker.csproj`
- `src/Mars.Device/Mars.Device.csproj`
- `.github/workflows/foundation-build.yml`

The central target framework is `net10.0`.

## Dependency direction

Encoded project references:
- Mars.Application -> Mars.Domain + Mars.Contracts
- Mars.Infrastructure -> Mars.Domain + Mars.Application
- Mars.Api -> Mars.Application + Mars.Contracts + Mars.Infrastructure
- Mars.Worker -> Mars.Application + Mars.Infrastructure
- Mars.Domain -> none
- Mars.Contracts -> none
- Mars.Device -> none

No circular project reference was introduced.

## Intentional omissions

- `Mars.Web` is not created in FW-IMP-001 because its TypeScript/Vite/Mars.UI implementation belongs to FW-IMP-006.
- Test projects are not created merely as empty placeholders; FW-IMP-001 contains no behavioral primitive to unit-test.
- EF Core/Npgsql packages, DbContext, mappings, SQL and migrations are not introduced; persistence implementation belongs to FW-IMP-003.
- Mars.Api and Mars.Worker are project boundaries only in this package; host behavior belongs to later Foundation packages.

## Verification

Local assistant execution environment:
- `dotnet` CLI was unavailable, so no local build result is claimed.

Self-hosted runner evidence:
- workflow: `Foundation Build`
- run id: `35702737435`
- verified head: `212022601c177ec6bc8e8438c6188e747cbee2c5`
- runner machine: `mars-ci`
- SDK: `10.0.401`
- runtime: `10.0.12`
- restore: PASS
- Release build: PASS
- warnings: 0
- errors: 0
- project-reference listing: PASS

The first setup attempt failed because the default Linux install path was not writable by the self-hosted runner. The workflow was corrected to install .NET under the runner user's writable `$HOME/.dotnet` path. The final verification run passed.

## Boundaries preserved

Not implemented:
- domain business rules
- PostgreSQL schema
- migration
- DbContext / EF mapping
- auth provider
- OpenAPI tooling
- logging/metrics provider
- Mars.Web/Mars.UI
- deployment

## Planning metric

Exact master section-8 planning completion remains:
- 9 / 30 = 30.0%.

FW-IMP-001 is implementation progress, not section-8 planning completion.
