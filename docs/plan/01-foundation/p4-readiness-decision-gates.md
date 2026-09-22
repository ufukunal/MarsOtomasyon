# P4 Foundation Readiness — Technology Decision Gates

Status: READY — REQUIRED OWNER DECISIONS RESOLVED
Scope: readiness classification only; no implementation, package installation, SQL, migration, deployment or heavy test.

## 1. Purpose

Classify the Foundation technology gates against the smallest useful P4 thin framework slice.

The first slice must be able to establish the repository/runtime skeleton and prove the accepted Foundation architecture can host later vertical slices without domain-specific hacks. It does not need to implement every future platform/provider capability.

## 2. Source / inference rule

SOURCE:
- `docs/plan/master-project-plan.md`
- `docs/plan/01-foundation/framework-plan.md`
- `docs/plan/01-foundation/plan.md`
- `docs/plan/01-foundation/test-environment.md`
- `docs/db/` frozen logical model
- current repository root: no `src/` or `tests/` tree exists yet
- `docs/plan/decisions/` contains no accepted technology ADR yet

INFERENCE:
- a gate is REQUIRED NOW only when the first P4 slice cannot create a stable project/runtime/persistence contract without choosing it.

No inference below is itself an accepted technology decision.

## 3. First P4 thin-slice dependency boundary

The first implementation slice is expected to need:
- solution/project topology;
- ASP.NET Core API host;
- .NET Worker host where needed for transactional outbox proof;
- PostgreSQL connection and migration path;
- actor/company/correlation context primitives;
- deterministic result/error/validation baseline;
- transaction/unit-of-work boundary;
- durable PostgreSQL idempotency primitive;
- audit primitive;
- transactional outbox primitive;
- liveness/readiness;
- typed configuration/startup validation;
- targeted test architecture.

The readiness task does not authorize implementation of these items.

## 4. Gate classification

### G1 — Exact .NET SDK/runtime version

Classification: **REQUIRED NOW**

Repository source:
- master architecture fixes .NET / ASP.NET Core / C# but deliberately leaves exact SDK/runtime as a decision gate.
- project topology and every future C# project require a target framework/runtime baseline.

Why required now:
- `.csproj` target framework, SDK pinning, ASP.NET Core runtime, Worker runtime, package compatibility and CI build image depend on it.
- postponing the choice would make the first source tree non-reproducible.

Current external fact:
- Microsoft support policy on 2026-09-08 lists .NET 10 as Active LTS through November 2028.
- .NET 11 is still RC/go-live, not GA, as of September 2026.
- .NET 8 and .NET 9 approach end of support in November 2026.

Realistic options:
1. **.NET 10 LTS** — current stable LTS baseline.
2. .NET 11 RC — pre-GA/go-live; higher change risk for a new long-lived ERP baseline.
3. .NET 8/9 — supported only briefly from the current project date and therefore imply near-term upgrade work.

Technical recommendation:
- .NET 10 LTS is the lowest-risk baseline consistent with a new long-lived ERP and current Microsoft support status.

Owner decision required: **RESOLVED — APPROVED**

Accepted owner decision:
- `.NET 10 LTS` is the P4 runtime/SDK baseline.
- Decision record: `docs/plan/decisions/ADR-0001-dotnet-10-lts-baseline.md`.

### G2 — Exact ORM / data-access strategy

Classification: **REQUIRED NOW**

Repository source:
- Foundation requires PostgreSQL connection management, transactions, migrations, module mapping, idempotency/outbox/audit persistence.
- `framework-plan.md` explicitly says exact ORM/data-access library is UNKNOWN and requires an explicit decision before implementation.
- PLAN-010 freezes normalized PostgreSQL logical contracts and migration-only schema evolution.

Why required now:
- first persistence project, transaction abstraction, migrations, mappings and test fixtures depend on the persistence stack.
- choosing it later would force early infrastructure code/migrations to be rewritten.

Realistic options:
1. **EF Core 10 + Npgsql EF Core provider** — integrated mapping/change tracking/migrations with PostgreSQL provider support.
2. Npgsql ADO.NET + hand-written SQL/micro-ORM approach — high SQL control but requires a separate migration/mapping discipline and more manual infrastructure.
3. Hybrid EF Core + targeted raw Npgsql/SQL for measured/specialized cases — keeps one migration/model baseline while allowing escape hatches, but must not become two competing persistence authorities.

Technical recommendation:
- use EF Core 10 + Npgsql as the default transactional/migration mapping baseline; allow targeted raw Npgsql/SQL only behind explicit measured need. This best matches the repository's migration, normalized model, transaction and smallest-correct-framework goals without creating two default persistence systems.

Owner decision required: **RESOLVED — APPROVED**

Accepted owner decision:
- `EF Core 10 + Npgsql` is the default persistence/migration baseline.
- targeted raw Npgsql/SQL is allowed only for explicitly justified specialized cases.
- Decision record: `docs/plan/decisions/ADR-0002-ef-core-npgsql-baseline.md`.

### G3 — Exact authentication / identity provider

Classification: **DEFERRABLE**

Repository source:
- Foundation already freezes server-side authorization, permission hooks, company/branch scope and immutable actor context.
- exact authentication/identity provider is explicitly UNKNOWN.

Why deferrable:
- the first thin infrastructure slice can define authentication/authorization interfaces and request context without committing to the final human identity provider.
- an actual authenticated business/API vertical slice must not ship before this gate is closed.

Must be resolved before:
- first real login/session flow or authenticated business endpoint intended beyond internal scaffold/testing.

Owner decision required now: **NO**

### G4 — Exact OpenAPI tooling

Classification: **DEFERRABLE**

Repository source:
- OpenAPI generation is required; exact package/tool is a decision gate.

Why deferrable:
- API host, error model, routing and DTO conventions can be established before committing to a specific generator/package.
- close this gate before API contract publication/tooling becomes part of CI/client generation.

Owner decision required now: **NO**

### G5 — Exact structured logging / metrics stack

Classification: **DEFERRABLE**

Repository source:
- logging fields and minimum metrics are frozen; exact stack is UNKNOWN.

Why deferrable:
- first slice can honor structured logging/correlation abstractions through the standard .NET logging surface and health endpoints without selecting production log/metrics backends.
- production/test observability adapters can be selected after the host exists.

Must be resolved before:
- operational test deployment is treated as observability-complete or production monitoring is designed.

Owner decision required now: **NO**

### G6 — Exact object/file storage backend

Classification: **DEFERRABLE**

Repository source:
- Foundation freezes file abstraction/security contract; backend is UNKNOWN.

Why deferrable:
- no first-slice persistence/outbox/health primitive depends on physical file storage.
- keep the interface boundary only until a file-consuming vertical slice is scheduled.

Owner decision required now: **NO**

### G7 — Exact Desktop shell technology

Classification: **NOT REQUIRED FOR P4**

Repository source:
- cross-platform shells are P10; Mars.Device/platform adapter boundary is already frozen.

Reason:
- Foundation core can expose client/platform contracts without selecting the Desktop shell.
- deciding now would create premature lock-in unrelated to the first server/framework slice.

Owner decision required now: **NO**

### G8 — Exact Mobile shell technology

Classification: **NOT REQUIRED FOR P4**

Repository source:
- Mobile shell is a P10 decision gate.

Reason:
- not required for Foundation server/runtime/persistence proof.

Owner decision required now: **NO**

### G9 — Optional scheduling library

Classification: **DEFERRABLE**

Repository source:
- Worker is fixed.
- framework says an exact scheduling library remains UNKNOWN unless simple Worker timers are insufficient.

Why deferrable:
- transactional outbox worker/retry proof does not require a general scheduler.
- do not add a scheduling dependency until a concrete job requires semantics beyond the Worker baseline.

Owner decision required now: **NO**

### G10 — Production secret store

Classification: **NOT REQUIRED FOR P4 FIRST SLICE**

Repository source:
- production secrets must remain outside repository.
- exact production secret store is a later decision gate.
- test environment has a separate explicit test-only credential policy.

Reason:
- first slice requires a configuration/secret abstraction and no-secret-logging rule, not the final production store.

Must be resolved before:
- production deployment/hardening.

Owner decision required now: **NO**

### G11 — Production reverse proxy / tunnel details

Classification: **NOT REQUIRED FOR P4 FIRST SLICE**

Repository source:
- production topology details are an explicit decision gate.
- P4 first slice does not authorize production deployment.

Reason:
- API/Worker/process topology and health contracts can be implemented independently of production ingress.

Must be resolved before:
- production topology/release hardening.

Owner decision required now: **NO**

## 5. Summary

### REQUIRED NOW
- G1 Exact .NET SDK/runtime version.
- G2 Exact ORM/data-access strategy.

### DEFERRABLE
- G3 Authentication/identity provider.
- G4 OpenAPI tooling.
- G5 Structured logging/metrics stack.
- G6 Object/file storage backend.
- G9 Optional scheduling library.

### NOT REQUIRED FOR P4 FIRST SLICE
- G7 Desktop shell technology.
- G8 Mobile shell technology.
- G10 Production secret store.
- G11 Production reverse proxy/tunnel details.

## 6. Readiness state

P4 implementation readiness: **READY**

Both required-now owner decisions are resolved and captured as Accepted ADRs:
1. .NET 10 LTS;
2. EF Core 10 + Npgsql default persistence/migration baseline with targeted raw Npgsql/SQL escape hatch.

Next repository-defined implementation package:
- `FW-IMP-001 — repository solution skeleton`

No code, package installation, SQL, migration or deployment was performed in the decision-closing session.

## 7. Exact planning metric

Master section-8 completion remains:
- 9 / 30 = 30.0%.

P4 readiness or implementation does not count as section-8 Quality completion.
