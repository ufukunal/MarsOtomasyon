# Current Handoff

## Repository
- Repository: ufukunal/MarsOtomasyon
- Allowed branch: main
- Current HEAD: verify from Git at session start
- Master plan: docs/plan/master-project-plan.md
- Session protocol: docs/ai/session-execution-protocol.md

## Completed phases
- P2 Core Commercial Workflow Planning: COMPLETED / FROZEN
- P3 Logical Database Model: COMPLETED / FROZEN

## Current phase
P4 — Foundation implementation
Status: BLOCKED ON TWO OWNER TECHNOLOGY DECISIONS

## Planning progress
- Master section-8 sequence: 9 / 30 = 30.0%
- P2 core commercial: 8 / 8 = 100.0%
- P4 readiness does not increment the exact section-8 planning metric.

## Gate classification
Canonical classification:
- docs/plan/01-foundation/p4-readiness-decision-gates.md

REQUIRED NOW:
1. exact .NET SDK/runtime version;
2. exact ORM/data-access strategy.

DEFERRABLE:
- auth/identity provider;
- OpenAPI tooling;
- structured logging/metrics stack;
- object/file storage backend;
- optional scheduling library.

NOT REQUIRED FOR P4 FIRST SLICE:
- Desktop shell technology;
- Mobile shell technology;
- production secret store;
- production reverse proxy/tunnel details.

## Technical recommendations awaiting owner acceptance
- .NET 10 LTS for runtime/SDK baseline.
- EF Core 10 + Npgsql for default PostgreSQL persistence/migrations; targeted raw Npgsql/SQL only for explicit specialized/measured need.

These are recommendations, not accepted project decisions.

## Owner decision required
P4 implementation remains BLOCKED until the owner explicitly chooses:
- runtime baseline;
- persistence/ORM baseline.

Do not create accepted ADRs or implementation code before both choices are explicit.

## Scope evidence
Repository root currently has no src/ or tests/ implementation tree.
No SQL, migration, C#/API/TypeScript, package installation, deployment or heavy test was performed during readiness classification.
