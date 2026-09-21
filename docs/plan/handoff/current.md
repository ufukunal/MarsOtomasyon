# Current Handoff

## Repository
- Repository: ufukunal/MarsOtomasyon
- Allowed branch: main
- Current HEAD: verify from Git at session start
- Master plan: docs/plan/master-project-plan.md
- Session protocol: docs/ai/session-execution-protocol.md

## Completed phase
P3 — Logical Database Model: COMPLETED / FROZEN

## Planning progress
Counting basis: master-project-plan section 8; only COMPLETED / FROZEN section-8 packages count.
- Master planning sequence: 9 / 30 = 30.0%
- P2 core commercial planning: 8 / 8 = 100.0%
- PLAN-010 Logical Database Model: 100% complete, but it is not section-8 item 10 and therefore does not increment the exact 30-package metric.

## PLAN-010 result
Canonical logical DB contracts:
- docs/db/00-domain-dictionary.md
- docs/db/01-design-principles.md
- docs/db/02-module-ownership.md
- docs/db/03-entity-catalog.md
- docs/db/04-relationships.md
- docs/db/05-ledgers-and-finance.md
- docs/db/06-snapshots-and-projections.md
- docs/db/07-constraints-and-concurrency.md
- docs/db/08-index-access-patterns.md
- docs/db/09-migration-conventions.md
- docs/db/acceptance-criteria.md
- docs/db/full-test-day.md

Frozen:
- bounded-context logical ownership; no universal nullable document table;
- separate Inventory, Account, Cash, Bank and Inventory Valuation ledger authorities;
- Party CUSTOMER/SUPPLIER multi-role model without automatic netting;
- Reservation separate from physical stock/disposition;
- normalized source-target quantity/value relations across Sales, Purchasing and Returns;
- Checks/Notes custody/lifecycle separate from Finance monetary position;
- no Invoice paid/open/open-item allocation authority;
- immutable snapshots and append/reversal history;
- PostgreSQL-owned durable idempotency/concurrency guarantees;
- rebuildable projections and non-authoritative Valkey;
- logical access-pattern/index intent and migration/backfill/lock conventions.

No physical SQL, migration, EF model, C#/API, TypeScript/UI, deployment or heavy test was added/run.

## Post-P3 scheduling blocker
Repository sources disagree about the next package:
1. master-project-plan phase map: P4 Foundation implementation follows P3;
2. master-project-plan section 8: conceptual item 10 is Quality, target docs/plan/08-kalite/;
3. tasks/backlog.md: PLAN-011 is Commerce/B2B/Architect/Marketplace planning.

Do not silently choose among these. Normalize the repository-defined post-P3 order first, then start the selected task.
