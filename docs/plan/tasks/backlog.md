# Planning Backlog

## Current P5 implementation

FINANCE-IMP-001 — Finance/Treasury Ledger, Settlement & Inventory Valuation Authority Tranche

Status:
- SCOPE FROZEN
- READY FOR IMPLEMENTATION

Canonical readiness:
- docs/plan/09-finans-kasa-banka/p5-finance-treasury-readiness.md

Implementation remains one broad package. Do not split Finance capabilities into micro work-package IDs.

Explicit deferred/fail-closed dependencies:
- non-TRY Finance posting, FX transfer, realized/unrealized FX until company base-currency/rate authority exists;
- provider-specific bank/file adapters;
- standalone landed-cost policy/document engine;
- Returns/RMA-dependent refund entitlement;
- statutory General Ledger/Chart of Accounts/e-ledger/tax filing;
- Checks/Promissory Notes;
- production deployment;
- Full Test Day.

## Portfolio planning

Status: COMPLETED / FROZEN.

Canonical:
- docs/plan/project-plan-completion.md

All 30 master planning items have frozen portfolio and detailed planning coverage.
Do not recreate global/module planning.

## Full Test Day

Status: DEFERRED BY POLICY.

Finance heavy backlog is already frozen in:
- docs/plan/09-finans-kasa-banka/full-test-day.md

Normal FINANCE-IMP-001 development runs only targeted build/unit/invariant/migration/API smoke evidence.

Planning progress:
- master portfolio: 30 / 30 = 100.0%
- master detailed planning: 30 / 30 = 100.0%
- implementation progress is tracked separately.
