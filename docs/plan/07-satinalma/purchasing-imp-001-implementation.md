# PURCHASING-IMP-001 — Purchasing Commercial Receipt & Supplier Invoice Authority Tranche

Status: COMPLETED

## Scope completed

Implemented as one broad tranche:
- Purchase Order commercial authority;
- confirmed-order controlled remainder-decrease amendments with effective version history;
- Purchase Order confirm / cancel-remainder / close lifecycle;
- supplier ACTIVE + SUPPLIER eligibility;
- Product ACTIVE + PURCHASABLE and Product/UOM eligibility;
- Goods Receipt DRAFT / READY / POST / cancel / reversal lifecycle;
- Warehouse resource-scope authorization for receipt mutations;
- cumulative Goods Receipt source caps with Purchase Order locking;
- STOCKABLE Goods Receipt POST delegated to Inventory physical authority;
- initial STOCKABLE receipt disposition = QUARANTINE;
- SERVICE/non-stock receipt lines do not create physical Inventory movements;
- Goods Receipt reversal uses linked compensating Inventory movements;
- linked active Supplier Invoice DRAFT blocks Goods Receipt reversal until source links are removed/replaced;
- Supplier Invoice DRAFT create / replace / cancel;
- DIRECT Supplier Invoice requires dedicated permission and reason, remains financial-only and blocks STOCKABLE Product;
- PO-sourced non-stock/service 2-way source validation;
- Goods-Receipt-sourced stockable 3-way source validation;
- cumulative active Supplier Invoice DRAFT source caps with serialized source allocation;
- match evidence, zero-tolerance price-variance blocking and direct-invoice exception approval evidence;
- approval SoD via shared Foundation approval authority;
- Purchase Return source-preview boundary without implementing Return physical/financial authority;
- protected API and Mars.Web /purchasing;
- additive Purchasing migration and TEST deployment.

Explicitly NOT implemented:
- Supplier Invoice POST/REVERSE;
- Finance Supplier Payable;
- Supplier Payment / settlement / allocation;
- Inventory valuation / landed-cost accounting;
- Quality module implementation;
- Warehouse put-away/pick/pack/stage/load/transfer/count implementation;
- tax/e-document/provider integrations;
- production deployment;
- Full Test Day.

## Canonical tested commit

50ec242e5471743bbdc9ad43bc69626166b1679f

## Migration evidence

Purchasing migration:
- 20260924201517_PurchasingImp001CommercialReceiptInvoiceAuthority
- generated migration commit: cb00a419241ead9f925731fe7db0c1e808125170

Committed migration safety count:
- 11 PASS

EF pending model:
- PASS — no pending model changes

The Purchasing migration generator is retained as manual/read-only verification tooling after migration commit.

## Foundation Build evidence

Workflow:
- Foundation Build
- run 36054248023
- job 107817148334
- conclusion SUCCESS

Evidence:
- frontend tests 22 / 22 PASS
- targeted Foundation tests 97 / 97 PASS
- Release build SUCCESS
- 0 warnings
- 0 errors
- PURCHASING calculation order PASS
- deferred Supplier Invoice POST/REVERSE permissions absent PASS
- normalized Purchasing model PASS
- Purchasing persistence authority contracts PASS
- Goods Receipt Warehouse scope PASS
- STOCKABLE Goods Receipt -> Inventory QUARANTINE delegation PASS
- SERVICE/non-stock Goods Receipt has no physical movement PASS
- Inventory failure stops Goods Receipt completion PASS
- Goods Receipt reversal delegates compensating Inventory movement PASS
- DIRECT Supplier Invoice requires explicit direct-create permission PASS
- EF pending model PASS
- protected Purchasing API smoke PASS
- OpenAPI Purchasing surface present
- Supplier Invoice POST/REVERSE absent

## TEST deployment evidence

Workflow:
- Foundation Test Deploy
- run 36054247926
- job 107818562329
- conclusion SUCCESS

Evidence:
- frontend 22 / 22 PASS
- targeted Foundation 97 / 97 PASS
- Release build 0 warnings / 0 errors
- migration safety 11 PASS
- EF pending model PASS
- deployment migration PASS
- deployed EF migration count 11
- /purchasing = 200
- /sales = 200
- /inventory = 200
- /products = 200
- /parties = 200
- /health/live = 200
- /health/ready = 200
- protected Purchasing Order / Receipt / Invoice / Match / Return-source routes unauthenticated = 401
- OpenAPI Purchasing surface expected
- Supplier Invoice POST/REVERSE absent
- runner-to-TEST smoke PASS

No real authenticated TEST Purchasing mutation is claimed.

## Deferred / downstream

Supplier Invoice POST/REVERSE remains blocked until Finance Supplier Payable/accounting authority exists.

Inventory valuation and landed-cost accounting remain Finance/Costing-owned.

Goods Receipt usable release from QUARANTINE remains dependent on future Quality/disposition workflow.

Warehouse put-away/pick/pack/stage/load/transfer/count/replenishment/damage/scrap/offline workflows remain Warehouse-owned.

Purchase Return implementation remains downstream Return/Inventory/Finance work; this tranche only exposes source lineage preview.

Tolerance policy administration remains Settings/Purchasing Policy work. In its absence over-receipt and cumulative over-invoice remain fail-closed.

Heavy authenticated permission matrix, concurrency, browser E2E, performance/load, backup/restore and full cross-module invariants remain Full Test Day.

## Decision

PURCHASING-IMP-001 is COMPLETE.

Next P5 dependency:
Warehouse broad implementation tranche scope/readiness definition. A WAREHOUSE-IMP package ID is not assigned until the exact broad coherent scope is frozen.
