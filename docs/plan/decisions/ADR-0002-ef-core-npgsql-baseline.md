# ADR-0002 — EF Core 10 + Npgsql PostgreSQL Persistence Baseline

Status: Accepted
Date: 2026-09-22
Owners: Project Owner

## Context

MarsOtomasyon uses PostgreSQL as the authoritative database and requires migration-only schema evolution, explicit transaction boundaries, durable idempotency, transactional outbox, audit persistence and normalized module-owned data.

The exact ORM/data-access stack was intentionally left unresolved until P4 readiness.

## Decision

MarsOtomasyon will use:

- **Entity Framework Core 10** as the default ORM and migration baseline;
- **Npgsql** as the PostgreSQL provider;
- PostgreSQL remains authoritative.

Targeted raw Npgsql/SQL is allowed only when:
- a specialized PostgreSQL operation requires it;
- EF Core is materially unsuitable for that operation; or
- measured performance evidence justifies it.

Raw SQL/Npgsql must remain an explicit specialized escape hatch and must not become a second competing default persistence architecture.

## Consequences

Positive:
- one default mapping/migration model;
- explicit PostgreSQL provider alignment;
- integrated unit-of-work/change-tracking/migration support where appropriate;
- easier consistency between module mappings and migration history;
- specialized SQL remains available without replacing the default persistence model.

Negative:
- EF Core behavior and generated SQL must be reviewed for critical ledger/concurrency paths;
- performance-sensitive operations may still require explicit SQL;
- provider/framework upgrades must be coordinated;
- ORM abstractions must not weaken database constraints or PostgreSQL authority.

## Alternatives considered

- Npgsql ADO.NET plus hand-written SQL as the default: not selected because it would require more manual mapping/migration infrastructure for the initial framework.
- Micro-ORM/SQL-first default: not selected because it would introduce a separate baseline migration/mapping discipline.
- Hybrid with two equal default persistence systems: rejected; targeted raw SQL is permitted only as an explicit exception.

## Affected areas

- Foundation
- PostgreSQL persistence
- migrations
- transaction/unit-of-work infrastructure
- idempotency
- outbox
- audit
- module persistence mappings
- database integration tests

## Revisit conditions

Revisit when:
- measured performance or PostgreSQL-specific functionality shows EF Core is unsuitable for a material class of operations;
- the provider cannot support a required PostgreSQL feature;
- a future architecture change materially alters persistence requirements.

Any revisit must preserve:
- PostgreSQL authority;
- migration-only schema changes;
- database constraints;
- append/reversal history;
- ledger/snapshot/projection boundaries.

## Sources

Repository:
- docs/plan/master-project-plan.md
- docs/plan/01-foundation/framework-plan.md
- docs/plan/01-foundation/p4-readiness-decision-gates.md
- docs/db/01-design-principles.md
- docs/db/02-module-ownership.md
- docs/db/03-entity-catalog.md
- docs/db/07-constraints-and-concurrency.md
- docs/db/09-migration-conventions.md
- docs/db/acceptance-criteria.md

Owner decision:
- current explicit project-owner instruction dated 2026-09-22 approving EF Core 10 + Npgsql with targeted raw SQL/Npgsql escape hatch.
