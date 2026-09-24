# Planning Backlog

## Next dependency after active SALES-IMP-001 — Purchasing broad implementation tranche definition
Status: BLOCKED BY ACTIVE SALES IMPLEMENTATION.

Dependency:
- SALES-IMP-001 must be implemented and runtime-verified before Purchasing becomes current in the P5 dependency order.

Direction when unblocked:
- reconcile frozen PLAN-005 + PLAN-010 against implemented Product/Inventory/Sales contracts
- define one broad coherent Purchasing implementation tranche
- do not assign the Purchasing implementation package ID before repository reconciliation and scope freeze

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
