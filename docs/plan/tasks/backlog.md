# Planning Backlog

## Current P5 implementation dependency

Warehouse broad implementation tranche scope/readiness definition.

Status: SCOPE DEFINITION REQUIRED.

Formal WAREHOUSE-IMP package ID is intentionally deferred until the exact broad coherent tranche is frozen.

Predecessor PURCHASING-IMP-001 is completed and runtime-verified:
- canonical report: docs/plan/07-satinalma/purchasing-imp-001-implementation.md
- tested commit: 50ec242e5471743bbdc9ad43bc69626166b1679f
- Foundation Build 36054248023 — SUCCESS
- Foundation Test Deploy 36054247926 — SUCCESS

Use frozen PLAN-006 and current Inventory/Sales/Purchasing implementation truth.
Do not repeat global/module planning.

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
