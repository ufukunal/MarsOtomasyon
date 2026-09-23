# 01 — Foundation

Foundation defines the shared rules every MarsOtomasyon module must obey. It must remain small: shared infrastructure belongs here; domain-specific behavior does not.

## Objectives
- establish module boundaries
- standardize commands/queries
- define transactions and outbox
- define idempotency
- define audit
- define authorization/permission primitives
- define company/branch scoping
- define numbering/document identity
- define files and notifications abstractions
- define error handling
- define API conventions
- define client platform abstractions
- define observability
- define migration/deployment conventions

## Explicit non-goals
Foundation will not contain:
- sales pricing rules
- inventory allocation logic
- accounting posting rules
- production planning logic
- marketplace-specific business logic
- UI screen-specific business behavior

Those remain in their bounded modules.

## Required skills
Primary:
- software-architect
- software-developer

Reviewers:
- database-architect
- security-specialist
- system-devops-specialist
- software-test-engineer
- erp-domain-specialist where shared business abstractions are proposed

## Status
Planning started. See `plan.md` and `acceptance-criteria.md`.


## P4 implementation

Readiness decisions are resolved:
- `docs/plan/decisions/ADR-0001-dotnet-10-lts-baseline.md`
- `docs/plan/decisions/ADR-0002-ef-core-npgsql-baseline.md`
- `docs/plan/decisions/ADR-0003-identity-openiddict-baseline.md`
- `docs/plan/decisions/ADR-0004-aspnet-openapi-baseline.md`

Implementation status:
- `FW-IMP-001 — repository solution skeleton`: COMPLETED
- `FW-IMP-002 — configuration/context/error primitives`: COMPLETED
- `FW-IMP-003 — persistence/migration baseline`: COMPLETED
- `FW-IMP-004 — audit/idempotency/outbox foundations`: COMPLETED
- `FW-IMP-005 — API foundation implementation`: COMPLETED
- `FW-IMP-006 — Mars.Web + Mars.UI foundation`: COMPLETED
- `FW-IMP-007 — Docker/test deployment baseline`: COMPLETED
- evidence:
  - `docs/plan/01-foundation/fw-imp-001-implementation.md`
  - `docs/plan/01-foundation/fw-imp-002-implementation.md`
  - `docs/plan/01-foundation/fw-imp-003-implementation.md`
  - `docs/plan/01-foundation/fw-imp-004-implementation.md`
  - `docs/plan/01-foundation/fw-imp-005-implementation.md`
  - `docs/plan/01-foundation/fw-imp-006-implementation.md`
  - `docs/plan/01-foundation/fw-imp-007-implementation.md`
- next: `FW-IMP-008 — thin vertical framework proof`; exact scope definition required before implementation.


## FW-IMP-006 current status

`FW-IMP-006 — Mars.Web + Mars.UI foundation` is COMPLETED.

Canonical evidence:
- `docs/plan/01-foundation/fw-imp-006-implementation.md`

Verified:
- Vite/TypeScript Mars.Web foundation with committed npm lockfile;
- Mars-owned shell/router/API client;
- semantic Mars.UI design tokens;
- Button/Field/Dialog/Tabs/Lookup/Grid primitives;
- no forbidden frontend framework or local/session storage token baseline;
- 10 / 10 targeted frontend tests;
- production Vite build;
- static architecture checks;
- existing .NET build/tests/migration/API verification remain green.

Next repository-defined package:
- `FW-IMP-007 — Docker/test deployment baseline` — READY.

Production secret-store, production reverse-proxy/tunnel and logging/metrics technologies remain deferred. FW-IMP-007 must verify test-environment Docker/Compose/deployment facts before mutation and must not promote test choices into production architecture.


## FW-IMP-007 current status

`FW-IMP-007 — Docker/test deployment baseline` is COMPLETED.

Canonical evidence:
- `docs/plan/01-foundation/fw-imp-007-implementation.md`

Verified:
- Docker API/migrator/Web image builds;
- TEST Compose baseline preserving existing PostgreSQL/Valkey ownership;
- separate migration/runtime PostgreSQL authority;
- remote TEST preflight and deployment;
- actual TEST liveness/readiness;
- protected API unauthenticated behavior;
- OpenAPI;
- runner-to-TEST smoke.

Final tested implementation:
- commit `2821c98bbd04b81dcbb10e99ddb0a4974ca7a517`;
- workflow run `35865600657`.

Next repository-tracked package:
- `FW-IMP-008 — thin vertical framework proof`.

Its exact implementation scope is not yet defined in repository sources. Define the smallest non-domain or deliberately minimal proof and acceptance criteria before mutation.

Production secret-store, production reverse-proxy/tunnel, production ingress/TLS and logging/metrics technologies remain deferred.
