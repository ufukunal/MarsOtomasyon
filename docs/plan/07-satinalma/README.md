# Purchasing Module Plan

Status: PLAN-005 COMPLETED / FROZEN — planning only.

## Purpose

Purchasing owns the commercial procurement workflow from supplier commitment through physical receipt evidence, supplier invoice matching and handoff to Finance payment.

Canonical chain:

Purchase Order
→ Goods Receipt / Service Acceptance
→ Supplier Invoice
→ Finance Payment

Purchase Return is a separate linked physical/financial correction flow.

## Process ownership

- Purchasing: Purchase Order, supplier commercial terms, source-target quantities, receipt/invoice matching, match exceptions.
- Parties: authoritative Supplier Party role and live legal identity.
- Products: authoritative Product/Variant/UOM/barcode master.
- Inventory/Warehouse: physical STOCK IN/OUT, Warehouse/Location, lot/serial and disposition.
- Quality: inspection/disposition where required.
- Finance: supplier payable ledger, Payment, settlement/allocation, FX accounting and authoritative valuation/costing.
- Settings: numbering and configurable Purchasing/Match Policy.

## Frozen invariants

- Purchase Order creates no physical STOCK, ACCOUNT payable, CASH/BANK or COST posting.
- Goods Receipt POST is physical STOCK IN for accepted STOCKABLE quantity.
- Stockable receipt enters QUARANTINE first; AVAILABLE requires explicit release/disposition.
- Goods Receipt alone creates no supplier payable.
- Supplier Invoice POST creates supplier payable and never posts physical stock.
- If receipt already created stock, invoice cannot increase stock again.
- STOCKABLE supplier invoice requires posted receipt evidence and 3-way match.
- SERVICE/non-stock invoice may use PO→Invoice 2-way match; controlled direct financial-only invoice is allowed and has STOCK=NONE.
- Over-receipt and over-invoice default to BLOCK.
- Any accepted over-tolerance requires explicit Purchasing Policy plus exception approval.
- Short/partial receipt and partial invoice are allowed and preserve remainder.
- Purchase Return physical STOCK OUT and financial supplier adjustment are separate linked effects.
- Payment is a separate Finance event; Purchasing does not own settlement/allocation.
- Posted physical/financial history is corrected by reversal/compensation, never silent edit/delete.
- PostgreSQL ledgers are authoritative; Valkey/UI/projections are not.

## V38 product reference

The repository V38 reference explicitly presents:
- Purchase Order
- Goods Receipt
- Supplier Invoice
- 3-Way Match
- Purchase Returns
- Supplier Performance

V38 also states:
- over-receipt/over-invoice default BLOCK; tolerance requires explicit policy/approval;
- posted Goods Receipt enters quarantine and usable release follows QC/disposition.

PLAN-005 adopts those product semantics while keeping production architecture modular and ledger-driven.

## Files

- plan.md
- workflows.md
- forms.md
- data-contract.md
- permissions.md
- integrations.md
- reports.md
- acceptance-criteria.md
- full-test-day.md

## Out of scope

- SQL/migrations
- C#/API/TypeScript implementation
- Warehouse operational implementation
- Finance Payment settlement/allocation implementation
- exact inventory valuation/costing method
- exact tolerance numeric values
- tax/legal provider implementation
- supplier portal/provider specifics


## PURCHASING-IMP-001 implementation closure

Status: COMPLETED.

Canonical implementation:
- docs/plan/07-satinalma/purchasing-imp-001-implementation.md

Tested commit:
- 50ec242e5471743bbdc9ad43bc69626166b1679f

Foundation Build:
- run 36054248023
- job 107817148334
- SUCCESS
- frontend 22 / 22 PASS
- targeted Foundation 97 / 97 PASS
- Release 0 warnings / 0 errors
- EF pending model PASS

Foundation Test Deploy:
- run 36054247926
- job 107818562329
- SUCCESS
- migration safety 11 PASS
- deployed migration count 11
- /purchasing 200
- /sales /inventory /products /parties 200
- live/ready 200
- protected Purchasing routes 401 unauthenticated
- OpenAPI Purchasing surface expected
- Supplier Invoice POST/REVERSE absent
- smoke PASS

No authenticated TEST Purchasing mutation is claimed.

Deferred boundaries remain Finance Supplier Payable/Payment/valuation, Quality implementation, Warehouse operations, Purchase Return execution, providers, production and Full Test Day.
