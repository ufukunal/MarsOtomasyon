# P5 Purchasing Authority Readiness

Status: READY FOR IMPLEMENTATION
Work package: PURCHASING-IMP-001 — Purchasing Commercial Receipt & Supplier Invoice Authority Tranche

## Source basis
- PLAN-005 frozen Purchasing module plan
- PLAN-010 logical DB model
- implemented Party/Product/Inventory/Sales authority
- current Foundation permission/audit/idempotency/outbox/runtime baseline
- V38 Purchasing product reference

## Existing authority available
- Party CUSTOMER/SUPPLIER role model and Party master
- Product/Variant/UOM master
- Inventory Warehouse/Location/Lot/Serial/Ledger/Reservation
- Foundation approval evidence primitive
- company-scoped permissions
- durable idempotency/audit/outbox
- PostgreSQL/EF migration baseline
- Mars.Web/Mars.UI patterns

## Broad tranche

Implement in one package:
1. Purchase Order header/lines/version authority
2. supplier eligibility and commercial snapshots
3. create/edit/approve/confirm/amend/cancel remainder/close
4. Goods Receipt header/lines/source links
5. partial receipt and cumulative PO source caps
6. stockable Goods Receipt POST through Inventory physical authority
7. initial QUARANTINE disposition for stockable receipt
8. lot/serial/source lineage
9. receipt reversal/compensation
10. 2-way/3-way match evidence and read model
11. over-receipt/over-invoice fail closed
12. Supplier Invoice DRAFT/source/calculation authority
13. Purchase Return source preparation/lineage only where it does not duplicate later Returns authority
14. API/Web/read surfaces
15. permissions/audit/idempotency/concurrency
16. additive migration and TEST deployment

## Frozen effect matrix
Purchase Order:
- DOC yes
- RES none by default
- STOCK none
- ACCOUNT none
- CASH/BANK none
- COST none

Goods Receipt POST:
- DOC yes
- STOCK IN through Inventory
- initial stockable disposition QUARANTINE
- ACCOUNT none
- CASH/BANK none
- valuation/cost posting remains Finance authority

Supplier Invoice DRAFT:
- DOC yes
- STOCK none
- ACCOUNT none until Finance-integrated POST exists
- CASH/BANK none

Supplier Invoice POST:
- NOT exposed in this tranche
- blocked until Finance Supplier Payable/valuation authority exists

Purchase Return:
- physical return authority remains later Returns/Purchasing integration boundary;
- this tranche may capture source eligibility/lineage but must not invent duplicate stock/financial posting.

## Matching
Stockable Supplier Invoice requires exact posted Goods Receipt evidence and 3-way match:
PO -> Receipt -> Supplier Invoice.

Service/non-stock allows 2-way:
PO -> Supplier Invoice.

Direct/source-less invoice:
- financial-only;
- cannot acquire stockable goods;
- final posting deferred with Finance.

Over-receipt and over-invoice:
- default BLOCK;
- no tolerance accepted unless authoritative Purchasing Match Policy exists;
- if future tolerance is configured, exception approval evidence required.

## Ownership boundaries
Inventory:
- physical STOCK IN/OUT
- Warehouse/Location
- lot/serial
- disposition

Quality:
- inspection and release/disposition decision
- not implemented by Purchasing

Finance:
- Supplier Payable
- Payment
- settlement
- FX accounting
- authoritative inventory valuation/landed cost

Purchasing:
- commercial procurement docs
- receipt commercial/source evidence
- match evidence/exceptions

## Expected schema
purchasing

Expected normalized structures:
- purchase_orders
- purchase_order_versions
- purchase_order_lines
- purchase_order_amendments
- goods_receipts
- goods_receipt_lines
- goods_receipt_inventory_effect_links
- supplier_invoices
- supplier_invoice_lines
- supplier_invoice_source_links
- purchase_match_results
- purchase_match_exceptions

Exact names may vary if Database Architect preserves equivalent normalized structure.

## Concurrency/idempotency
Durably protect:
- PO version stale writes
- amendment activation
- cumulative receipt cap
- duplicate Goods Receipt POST
- receipt reversal duplicate
- invoice source cap
- duplicate match decision
- external retry idempotency

## UI/API
Expected API families:
- /api/v1/purchasing/orders
- /api/v1/purchasing/receipts
- /api/v1/purchasing/invoices
- /api/v1/purchasing/matches

No Supplier Invoice POST route until Finance authority exists.

Mars.Web /purchasing:
- PO list/detail/version/amendment
- Goods Receipt create/post/reverse
- source remaining visibility
- match status/evidence
- Supplier Invoice DRAFT
- no payable/payment/current-balance authority

## Acceptance
- supplier ACTIVE SUPPLIER role eligibility
- Product PURCHASABLE/company/UOM eligibility
- PO no stock/account effect
- PO amendment/version stale guards
- partial receipt/source cap
- Goods Receipt Inventory STOCK IN
- QUARANTINE initial disposition
- lot/serial lineage
- duplicate POST protection
- receipt reversal
- 2-way/3-way match
- over-receipt/invoice fail-closed
- Supplier Invoice POST absent
- frontend verify
- Release build
- migration safety
- pending model clean
- API/OpenAPI smoke
- TEST /purchasing 200
- regressions /sales /inventory /products /parties 200
- protected Purchasing routes unauthenticated 401

## Deferred / Full Test Day
Broad authenticated permission/IDOR matrix, heavy PostgreSQL concurrency, browser E2E, performance/load, backup/restore and full accounting/value invariants.

## Decision
PURCHASING-IMP-001 is READY FOR IMPLEMENTATION.
