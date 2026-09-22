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

Implementation status:
- `FW-IMP-001 — repository solution skeleton`: COMPLETED
- `FW-IMP-002 — configuration/context/error primitives`: COMPLETED
- evidence:
  - `docs/plan/01-foundation/fw-imp-001-implementation.md`
  - `docs/plan/01-foundation/fw-imp-002-implementation.md`
- next: `FW-IMP-003 — persistence/migration baseline`
