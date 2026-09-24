# Active Tasks

## FINANCE-IMP-001 — Finance/Treasury Ledger, Settlement & Inventory Valuation Authority Tranche

**Status:** READY FOR IMPLEMENTATION / SCOPE FROZEN

Canonical readiness:
- docs/plan/09-finans-kasa-banka/p5-finance-treasury-readiness.md

Scope-freeze baseline:
- main HEAD: 35eb688cc2f96d5128c24dc14fe4e6d040d28c34
- migration baseline: 13

Completed predecessor:
- WAREHOUSE-IMP-001 — COMPLETED / RUNTIME VERIFIED
- canonical report: docs/plan/06-ambar-depo/warehouse-imp-001-implementation.md
- tested commit: 81efd9c347118db24d8945f4d49e63bf99baaf70
- Foundation Build 36068722249 — SUCCESS
- Foundation Test Deploy 36068722239 — SUCCESS

Broad included authority:
- Account/Cash/Bank/Inventory Valuation ledgers and Finance transaction authority;
- Collection/Payment/advances/refunds/role netting;
- Cash/Bank masters, Cash Count and same-currency treasury transfer;
- posting periods, immutable reversal and durable idempotency;
- statement evidence/reconciliation;
- customer credit/risk/hold;
- Goods Receipt provisional valuation;
- Dispatch valuation + dispatched-not-invoiced cost bridge;
- Sales Invoice receivable + COGS POST/REVERSE;
- Supplier Invoice payable + receipt-linked late price-cost delta POST/REVERSE;
- Count/Scrap financial valuation integration;
- protected Finance API and Mars.Web workspace.

Fail-closed/excluded:
- non-TRY authoritative Finance posting and FX/revaluation execution until company base-currency/rate authority exists;
- provider-specific bank/file adapters;
- standalone landed-cost document/policy administration;
- RMA-based refund entitlement until Returns exists;
- statutory GL/CoA/e-ledger/tax filing;
- production deployment;
- Full Test Day.

Next action:
- implement FINANCE-IMP-001 on main as one broad package;
- generate additive Finance migration from baseline 13;
- run only normal targeted build/test/migration/API smoke evidence.

Planning remains 30 / 30 = 100% for both portfolio and detailed master planning.
