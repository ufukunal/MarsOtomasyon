# Returns / RMA Module Plan

Status: PLAN-009 COMPLETED / FROZEN — planning only.

## Purpose
Own return authorization, return-case lifecycle, source/target lineage and physical/financial progress coordination without becoming a second Inventory or Finance authority.

## Frozen model
- Customer Return and Supplier Return use explicit return cases/lines.
- Physical and financial completion are separate linked dimensions.
- Customer physical receipt POST is the STOCK IN recognition point; accepted stock enters QUARANTINE.
- Supplier return shipment POST is the STOCK OUT recognition point.
- Inventory Ledger remains physical quantity truth; Finance remains Account/Cash/Bank/valuation truth.
- Source-linked return is the normal path. Source-less return is an approved exception, never an inferred source.
- Partial returns are supported; original/returned/remaining quantities remain explicit.
- Normal cumulative source-linked physical return cannot exceed eligible source quantity.
- Lot/serial/Product/Variant/UOM/source/company compatibility is mandatory.
- Customer financial credit reduces CUSTOMER_RECEIVABLE by CREDIT; cash/bank refund is a separate Finance event.
- Supplier financial adjustment reduces SUPPLIER_PAYABLE by DEBIT; supplier cash/bank refund is a separate Finance event.
- Refund is permitted only against an eligible customer credit/advance position; no Invoice allocation/open-item authority is introduced.
- Purchase-return valuation leaves the current valuation pool using Finance's current moving-average policy.
- Customer-return valuation restores/adjusts inventory and COGS through Finance using preserved Sales source/cost lineage; Returns does not invent cost.
- Replacement/exchange is coordinated as return plus a normal new Sales workflow; no hidden second sales engine.
- Posted physical/financial effects are corrected by linked reversal/compensation, never silent deletion.

## Outputs
plan.md, workflows.md, forms.md, data-contract.md, permissions.md, integrations.md, reports.md, acceptance-criteria.md, full-test-day.md.

No SQL, application code, migration, deployment or heavy tests were produced.