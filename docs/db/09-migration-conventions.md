# Migration and Physical-Schema Conventions

Status: FROZEN planning only — PLAN-010. No migration was created.

## 1. Ownership
Each migration must state owning module and affected logical contract.
Cross-module migration requires explicit dependency/order review.

## 2. Naming
Migration names describe business/schema intent rather than timestamps alone.
Physical table/schema naming convention is finalized with the implementation/ORM decision gate.

## 3. Forward evolution
Prefer additive/compatible changes:
1. add structure;
2. backfill safely;
3. deploy readers/writers in compatible order;
4. enforce stronger constraint after data is valid;
5. remove deprecated structure only after dependency proof.

## 4. Destructive changes
Drop, narrowing type, identity rewrite, ledger/history mutation and destructive renames require explicit risk classification and owner approval.
Posted ledger/snapshot history is not rewritten merely to simplify a migration.

## 5. Backfill
Backfill must specify:
- authoritative source;
- deterministic transformation;
- batch/chunk strategy where volume requires;
- idempotent/resumable behavior;
- validation counts/checksums/invariants;
- handling of invalid legacy rows.

Projection backfill is distinct from authoritative ledger reconstruction.

## 6. Lock and availability review
Before implementation review:
- table size/write frequency;
- lock level/duration;
- index-build behavior;
- FK/constraint validation cost;
- application deployment ordering;
- test-server rehearsal.

## 7. Data type gates
Exact NUMERIC precision/scale, timestamp/timezone type and enum/reference representation are chosen from frozen range/semantics before first physical migration.
Do not guess these from framework defaults.

## 8. Rollback / forward-fix
For financial/inventory production data, migration rollback must not imply deleting valid posted business history.
Prefer safe forward-fix when reverse migration would lose authoritative data.

## 9. Environment
Migrations are executed by approved deployment/migration identity, not runtime application privilege where Foundation contract separates roles.
Test-server result is not production proof.

## 10. PLAN-010 boundary
This document freezes migration discipline only. PLAN-010 creates no SQL, ORM mapping or migration file.
