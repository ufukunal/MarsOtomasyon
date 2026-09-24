# Planning Backlog

## Current P5 implementation

WAREHOUSE-IMP-001 — Warehouse Execution & Inventory Control Authority Tranche

Status: READY FOR IMPLEMENTATION.

Readiness:
- docs/plan/06-ambar-depo/p5-warehouse-execution-readiness.md

Predecessor PURCHASING-IMP-001 is completed and runtime-verified.

No Warehouse micro-package IDs are permitted for the frozen broad tranche.

Next dependency after runtime closure of WAREHOUSE-IMP-001:
- Finance / Treasury broad implementation tranche definition from frozen PLAN-007, subject to repository reconciliation.

## Portfolio planning

Status: COMPLETED / FROZEN.

Canonical:
- docs/plan/project-plan-completion.md

All 30 master planning items now have frozen portfolio and detailed planning coverage.
Future sessions must not recreate global/module planning; only active-module repository reconciliation and implementation readiness are required.

## Commerce/B2B/Architect/Marketplace
Target: docs/plan/15-e-ticaret-b2b-api/
Status: PORTFOLIO-PLANNED / P7 IMPLEMENTATION NOT STARTED.

## Full Test Day
Status: DEFERRED BY POLICY.

Party heavy risks include:
- concurrent Party Code create;
- concurrent role activation/lifecycle transition;
- concurrent deterministic Tax Identity collision;
- concurrent Party deactivation/state mutation;
- stale Party/role/tax identity state;
- cross-company IDOR;
- broad permission matrix;
- authenticated browser Party lifecycle E2E;
- consuming module eligibility races;
- provider reconciliation when introduced;
- high-volume duplicate/identity search;
- PII/security regression;
- snapshot persistence integration.

Planning progress:
- master portfolio: 30 / 30 = 100.0%
- master detailed planning: 30 / 30 = 100.0%
- P2 detailed core commercial: 8 / 8 = 100.0%
- implementation progress is tracked separately.
