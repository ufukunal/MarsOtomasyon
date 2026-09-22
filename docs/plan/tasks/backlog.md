# Planning Backlog

## Immediate — FW-IMP-003 persistence/migration baseline
Status: READY.

Predecessors:
- FW-IMP-001 repository solution skeleton — COMPLETED.
- FW-IMP-002 configuration/context/error primitives — COMPLETED.

Accepted persistence baseline:
- EF Core 10 + Npgsql.
- targeted raw Npgsql/SQL is allowed only for explicitly justified specialized cases and must not become a second default persistence architecture.

Scope:
- establish EF Core/Npgsql package and PostgreSQL persistence baseline;
- create connection/migration mechanism;
- preserve explicit migration/runtime privilege and transaction boundaries;
- create only Foundation-owned persistence baseline structures where FW-IMP-003 requires them;
- targeted persistence/migration contract tests.

Do not pull domain module schema/mappings or FW-IMP-004 audit/idempotency/outbox implementation into this package.

## Subsequent Foundation implementation sequence
Repository-defined sequence in `docs/plan/01-foundation/framework-plan.md`:
- FW-IMP-004 — audit/idempotency/outbox foundations
- FW-IMP-005 — API foundation
- FW-IMP-006 — Mars.Web + Mars.UI foundation
- FW-IMP-007 — Docker/test deployment baseline
- FW-IMP-008 — thin vertical framework proof

Deferred technology gates must be resolved only when their relevant implementation step requires them.

## Quality — master planning sequence item 10
Target: docs/plan/08-kalite/
Status: PLANNING BACKLOG / NOT STARTED.

Quality remains the next section-8 item that can raise exact planning completion from 9/30.
Operational execution belongs P6.

## Commerce/B2B/Architect/Marketplace
Target: docs/plan/15-e-ticaret-b2b-api/
Status: P7 / NOT IMMEDIATE.

## Full Test Day
Status: DEFERRED BY POLICY.

Exact section-8 planning progress:
- master: 9 / 30 = 30.0%
- P2: 8 / 8 = 100.0%
