# FW-IMP-003 — Persistence / Migration Baseline

Status: COMPLETED
Date: 2026-09-22

## Scope implemented

Persistence implementation remains owned by:
- `src/Mars.Infrastructure/`

Added:
- EF Core / Npgsql package baseline;
- `MarsDbContext`;
- runtime PostgreSQL options;
- migration PostgreSQL options;
- runtime option validation;
- EF Core options construction;
- design-time DbContext factory;
- local dotnet-ef tool manifest;
- migration-directory policy;
- targeted persistence/model/migration verification.

## Exact package and tool versions

Committed:
- `Microsoft.EntityFrameworkCore 10.0.12`
- `Microsoft.EntityFrameworkCore.Relational 10.0.12`
- `Microsoft.EntityFrameworkCore.Design 10.0.12`
- `Npgsql.EntityFrameworkCore.PostgreSQL 10.0.3`
- `dotnet-ef 10.0.12`

The explicit Relational 10.0.12 reference keeps the EF relational assembly on the same patch line while using Npgsql provider 10.0.3.

## Persistence boundary

`Mars.Domain` remains persistence-technology independent.

`Mars.Application` receives no EF Core/Npgsql package reference.

`Mars.Infrastructure` owns:
- EF Core;
- Npgsql provider;
- DbContext;
- provider-specific option construction;
- design-time migration tooling.

No competing repository/micro-ORM/raw-SQL persistence architecture was introduced.

## Runtime vs migration authority

Separate configuration types exist:
- `PostgreSqlRuntimeOptions`
- `PostgreSqlMigrationOptions`

Runtime validation reports only configuration key/code and never emits connection-string content.

Design-time EF tooling reads migration configuration only from:
- `MARS_MIGRATION_CONNECTION_STRING`

No actual credential is committed by FW-IMP-003.

The distinction enforces the Foundation direction that schema/migration authority is not assumed to be the restricted runtime application identity.

## DbContext and schema impact

`MarsDbContext` is intentionally empty in FW-IMP-003.

Committed model contains:
- 0 ERP domain entities;
- 0 Party/Product/Inventory/Sales/Purchasing/Warehouse/Finance mappings;
- 0 audit/idempotency/outbox mappings.

No committed migration was added because FW-IMP-003 establishes the mechanism and FW-IMP-004 owns the first Foundation persistence structures.

## Migration mechanism

Canonical migration location:
- `src/Mars.Infrastructure/Persistence/Migrations/`

Local tool:
- `.config/dotnet-tools.json`

CI performs a temporary migration-generation probe using a non-secret, non-connected design-time connection string.

The probe:
- generated an EF migration successfully;
- was inspected to ensure it contained no `CreateTable` operation;
- was removed from the CI workspace after inspection;
- was not committed or applied to PostgreSQL.

No database migration was executed in this session.

## Targeted tests

Existing Foundation harness was extended to verify:
1. PostgreSQL runtime connection string is required.
2. `MarsDbContext` selects the Npgsql EF provider.
3. `MarsDbContext` model contains zero entities at this baseline.
4. migration design-time factory refuses to run without migration-specific configuration.

Combined Foundation targeted test count:
- 11 / 11 PASS in final run.

## Verification history

### Run 35708147597
- tool restore: PASS
- solution restore: PASS
- build: PASS with EF relational version warnings
- targeted test: FAIL
- failure: runtime assembly mismatch between EF Relational 10.0.4 and 10.0.12
- migration probe: skipped

Correction:
- explicitly aligned `Microsoft.EntityFrameworkCore.Relational` to 10.0.12.

### Run 35708251674
- restore: PASS
- build: PASS
- targeted tests: PASS
- migration probe: FAIL
- failure: probe used the default Debug output while the workflow had built Release
- project-reference step: skipped

Correction:
- probe explicitly targets Release configuration.

### Final run 35708370550
Tested commit:
- `63734a34b1c86437504cc304cac1315b3d182ee1`

Environment:
- self-hosted runner: `mars-ci`
- .NET SDK: `10.0.401`
- runtime: `10.0.12`

Results:
- dotnet-ef tool restore: PASS
- solution restore: PASS
- Release build: PASS
- warnings: 0
- errors: 0
- targeted Foundation tests: PASS — 11 / 11
- temporary EF migration-generation probe: PASS
- project-reference verification: PASS

## Boundaries preserved

Not implemented:
- ERP domain tables/mappings;
- domain SQL/DDL;
- applied PostgreSQL migration;
- audit/idempotency/outbox persistence (FW-IMP-004);
- auth/identity provider;
- OpenAPI tooling;
- Mars.Web/Mars.UI;
- deployment.

## Planning metric

Exact master section-8 planning completion remains:
- 9 / 30 = 30.0%.

FW-IMP-003 is implementation progress, not section-8 planning completion.
