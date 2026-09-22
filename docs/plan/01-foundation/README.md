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
- evidence:
  - `docs/plan/01-foundation/fw-imp-001-implementation.md`
  - `docs/plan/01-foundation/fw-imp-002-implementation.md`
  - `docs/plan/01-foundation/fw-imp-003-implementation.md`
  - `docs/plan/01-foundation/fw-imp-004-implementation.md`
  - `docs/plan/01-foundation/fw-imp-005-implementation.md`
- next: `FW-IMP-006 — Mars.Web + Mars.UI foundation`; READY.


## FW-IMP-005 current status

`FW-IMP-005 — API foundation implementation` is COMPLETED.

Canonical evidence:
- `docs/plan/01-foundation/fw-imp-005-implementation.md`

Verified:
- Identity + OpenIddict follows ADR-0003 while ERP authorization remains Mars-owned;
- trusted principal maps to the existing actor/company/optional-branch/correlation context;
- Identity/OpenIddict persistence is Foundation-owned EF Core/Npgsql schema;
- Microsoft.AspNetCore.OpenApi 10.0.12 generates the v1 document;
- `/api/v1`, deterministic error mapping and separate liveness/readiness baselines exist;
- final Release build passed with 0 warnings / 0 errors;
- 26 / 26 targeted Foundation tests passed;
- committed migration scope/model drift, API smoke, OpenAPI generation and project references passed;
- no ERP business endpoint/schema/rule was introduced.

Next repository-defined package:
- `FW-IMP-006 — Mars.Web + Mars.UI foundation` — READY.

FW-IMP-006 planned scope:
- Vite/TypeScript;
- shell/router/API client;
- design tokens;
- Button/Field/Dialog/Tabs/Lookup/Grid baseline.

No FW-IMP-006-specific unresolved owner gate is currently identified.
Desktop/Mobile shell technology remains later P10 work; deployment remains later Foundation work.

