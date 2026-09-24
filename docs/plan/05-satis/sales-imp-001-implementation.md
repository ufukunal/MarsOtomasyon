# SALES-IMP-001 — Sales Commercial & Fulfillment Authority Tranche

Status: COMPLETED

## Scope completed

Implemented as one broad tranche:
- Quote/revision/line authority;
- partial/repeated Quote conversion with source caps;
- Sales Order/effective version authority;
- approval evidence and SoD;
- fail-closed direct Order confirmation without approval;
- inherited accepted Quote approval path;
- explicit Inventory Reservation integration;
- Warehouse resource-scope authorization;
- Dispatch commercial authority;
- Dispatch POST delegated to Inventory physical authority with Reservation consume;
- atomic failure behavior before consume/completion when Inventory fails;
- Dispatch reversal;
- Invoice DRAFT/source/calculation authority only;
- informational source-backed Proforma;
- protected API, Mars.Web and read surfaces;
- additive Sales migrations.

Explicitly NOT implemented:
- Sales Invoice POST/REVERSE;
- Finance Account Ledger;
- Inventory Valuation / Dispatch Cost Bridge / COGS;
- Collection/settlement;
- Warehouse Pick/Pack/Stage/Load;
- TCMB/e-document/provider integrations;
- production deployment;
- Full Test Day.

## Canonical tested commit

c99b1a3191fc5c6823734bd27f074ef10a8094a3

## Migration evidence

Sales migrations:
- 20260924152928_SalesImp001CommercialFulfillmentAuthority
- 20260924161902_SalesImp001ProformaAuthority

Committed migration safety count:
- 10 PASS

EF pending model:
- PASS — no pending model changes

## Foundation Build evidence

Workflow:
- Foundation Build
- run 36026678334
- job 107724878496
- conclusion SUCCESS

Evidence:
- frontend tests 21 / 21 PASS
- targeted Foundation tests 87 / 87 PASS
- Release build SUCCESS
- 0 warnings
- 0 errors
- SALES-IMP-001 calculation order PASS
- deterministic document discount residual PASS
- deferred Invoice/Warehouse permissions absent PASS
- Foundation approval SoD PASS
- Sales normalized model PASS
- Sales persistence authority contracts PASS
- Reservation permission contract PASS
- Proforma informational/source-backed PASS
- direct Order confirmation fail-closed PASS
- inherited Quote approval behavior PASS
- Dispatch Warehouse scope PASS
- Dispatch POST Inventory + Reservation delegation PASS
- Dispatch atomic failure behavior PASS
- EF pending model PASS

## TEST deployment evidence

Workflow:
- Foundation Test Deploy
- run 36026678557
- job 107724879932
- conclusion SUCCESS

Evidence:
- Sales Proforma migration applied
- deployed migration count 10
- /sales = 200
- /parties = 200
- /products = 200
- /inventory = 200
- /health/live = 200
- /health/ready = 200
- protected Sales Quote/Order/Reservation/Dispatch/Invoice/Proforma routes unauthenticated = 401
- OpenAPI = 200
- expected Sales surface present
- Invoice POST/REVERSE absent
- runner-to-TEST smoke PASS

No real authenticated TEST Sales mutation is claimed.

## Deferred / downstream

Finance-dependent Invoice POST/REVERSE remains blocked until Finance Account Ledger, Inventory Valuation and Dispatch Cost Bridge authority exist.

Warehouse operational Pick/Pack/Stage/Load remains Warehouse-owned.

Heavy authenticated permission matrix, concurrency, browser E2E, performance/load, backup/restore and full cross-module invariants remain Full Test Day.

## Decision

SALES-IMP-001 is COMPLETE.

Next P5 dependency:
Purchasing broad implementation tranche.
