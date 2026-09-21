# Planning Backlog

## PLAN-010 — Logical database model
Target: docs/db/

Status: READY / NEXT.

P2 workflow dependency is satisfied after PLAN-009 freeze.

Produce:
- domain dictionary v2;
- entity catalog;
- relationship model;
- module/schema ownership;
- commercial document strategy;
- Inventory/Account/Cash/Bank ledger logical models;
- Reservation;
- historical snapshots;
- projections/read models;
- outbox/idempotency/audit;
- key/public-id strategy;
- logical constraints;
- indexes from access patterns;
- concurrency rules;
- migration conventions.

Planning progress:
- master sequence: 9 / 30 = 30.0%
- P2 core commercial: 8 / 8 = 100.0%

No physical SQL/migration or application implementation in PLAN-010 unless explicitly authorized.

## PLAN-011 — Commerce/B2B/Architect/Marketplace planning
Target: docs/plan/15-e-ticaret-b2b-api/

Provider capabilities must be verified before implementation.

## PLAN-012 — Full Test Day plan consolidation
Collect heavy scenarios from all modules. Do not execute until explicitly requested.
