# Foundation Migration Baseline

This directory is the canonical EF Core migration location for the Mars Infrastructure persistence assembly.

Rules:
- schema changes are introduced through EF Core migrations;
- each committed migration must identify the owning module and affected logical contract in its session/evidence record;
- runtime application credentials are not migration credentials;
- design-time migration tooling reads only `MARS_MIGRATION_CONNECTION_STRING`;
- connection-string values must not be committed, echoed, or copied into evidence;
- domain module tables/mappings are not introduced by FW-IMP-003;
- audit, idempotency and outbox structures belong to FW-IMP-004;
- targeted raw Npgsql/SQL is exception-only under ADR-0002 and is not a parallel migration architecture.

FW-IMP-003 establishes the mechanism only. No committed schema migration is required until an accepted Foundation/domain structure actually needs one.
