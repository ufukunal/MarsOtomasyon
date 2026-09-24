# Planning Backlog

## Current P5 implementation

Finance / Treasury broad implementation tranche definition

Status: SCOPE DEFINITION REQUIRED.

Implementation work-package ID:
- UNASSIGNED until exact broad scope is frozen.

Planning source:
- frozen PLAN-007
- docs/plan/09-finans-kasa-banka/

Completed predecessor:
- WAREHOUSE-IMP-001 — COMPLETED / RUNTIME VERIFIED
- docs/plan/06-ambar-depo/warehouse-imp-001-implementation.md
- tested commit 81efd9c347118db24d8945f4d49e63bf99baaf70
- Foundation Build 36068722249 — SUCCESS
- Foundation Test Deploy 36068722239 — SUCCESS
- migration baseline 13

Next action:
- reconcile current Finance-related cross-module authority;
- freeze one broad coherent Finance / Treasury implementation tranche;
- assign the implementation package ID only after exact scope freeze.

No global/module replanning is required.

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
