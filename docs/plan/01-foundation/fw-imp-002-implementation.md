# FW-IMP-002 — Configuration / Context / Error Primitives

Status: COMPLETED
Date: 2026-09-22

## Scope implemented

Application-layer Foundation primitives were added under:

- `src/Mars.Application/Foundation/Configuration/`
- `src/Mars.Application/Foundation/Context/`
- `src/Mars.Application/Foundation/Results/`

### Configuration

Implemented:
- typed generic startup-configuration validator contract;
- deterministic validation issue contract;
- fail-fast startup validation coordinator;
- startup configuration exception that reports key/code identifiers without including option values.

No production secret-store, provider-specific configuration system or deployment wiring was selected.

### Context

Implemented immutable request/job context primitives:
- `CorrelationId`
- `IExecutionContext`
- `ExecutionContext`

The execution context carries:
- ActorId
- CompanyId
- optional BranchId
- CorrelationId

It does not implement authentication, authorization, session/token handling or Warehouse scope.

### Results / errors

Implemented deterministic Foundation categories:
- Validation
- Authentication
- Authorization
- NotFound
- Conflict
- Concurrency
- BusinessRule
- RateLimit
- Infrastructure
- ProviderFailure

Implemented:
- `ApplicationError`
- `Result`
- `Result<T>`

No RFC/problem-details/OpenAPI envelope technology was selected.

## Placement

All FW-IMP-002 primitives live in `Mars.Application`.

Reason:
- they are application orchestration contracts;
- they are not domain business rules;
- they are not public transport DTOs;
- they require no Infrastructure implementation.

No existing source project reference direction was changed.

## Targeted tests

Added:
- `tests/Mars.Foundation.Tests/Mars.Foundation.Tests.csproj`
- `tests/Mars.Foundation.Tests/Program.cs`

The repository had no accepted test framework. FW-IMP-002 therefore uses a zero-external-dependency executable unit-level harness instead of silently selecting a NuGet test framework.

No NuGet dependency was added.

Covered behaviors:
1. CorrelationId rejects blank values.
2. ExecutionContext preserves actor/company/branch/correlation scope.
3. ExecutionContext rejects empty UUID identities.
4. Result success/failure shape is deterministic.
5. Generic Result carries a success value.
6. Error-category set matches the accepted Foundation contract.
7. Startup configuration validation aggregates issues.
8. Startup configuration exception does not include the test option's secret value.

## Verification

First run:
- workflow run: `35704776504`
- result: FAILED at compile
- cause: C# syntax error in the initial `ConfigurationValidationIssue` declaration
- targeted tests were skipped because build failed.

Correction:
- commit: `a74e1083790aaf652dcac7dc0d735f759fc765e7`

Final verified run:
- workflow: `Foundation Build`
- run id: `35704843486`
- tested commit: `a74e1083790aaf652dcac7dc0d735f759fc765e7`
- runner: self-hosted `mars-ci`
- SDK: `10.0.401`
- runtime: `10.0.12`
- restore: PASS
- Release build: PASS
- warnings: 0
- errors: 0
- targeted tests: PASS — 8 / 8
- project-reference listing: PASS

## Boundaries preserved

Not implemented:
- EF Core/Npgsql packages
- DbContext or mappings
- SQL/DDL
- migrations
- auth/identity provider
- OpenAPI tooling
- structured logging/metrics backend
- file storage
- Mars.Web/Mars.UI
- deployment
- domain business rules

## Planning metric

Exact master section-8 planning completion remains:
- 9 / 30 = 30.0%.

FW-IMP-002 is implementation progress, not section-8 planning completion.
