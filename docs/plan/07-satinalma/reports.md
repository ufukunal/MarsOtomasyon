# Purchasing Reporting Contract

Status: FROZEN PLAN-005 planning semantics.

Reports are projections, not document/ledger authority.

## 1. Purchase Order Status

Grain:
PO line.

Measures:
- ordered_qty;
- received_qty;
- invoiced_qty;
- returned_qty_physical;
- remaining_to_receive;
- remaining_to_invoice;
- cancelled remainder;
- receipt/invoice progress.

Source:
PO authority + source-target links + posted receipt/invoice records.

## 2. Receipt Report

Grain:
Goods Receipt line.

Fields:
- supplier;
- PO;
- Product/Variant;
- entered/base qty;
- Warehouse/Location;
- disposition;
- lot/serial/expiry;
- POST/reversal state;
- QC/disposition progress.

Stock quantity source is Inventory Ledger.

## 3. Supplier Invoice / Match Report

Grain:
Supplier Invoice line.

Show:
- source mode;
- PO qty/price;
- Receipt qty;
- Invoice qty/price;
- quantity variance;
- price variance;
- tolerance/policy;
- match status;
- approval;
- payable posting state.

Do not recompute supplier payable from Purchasing.

## 4. Supplier Performance

V38 product reference includes:
- PO count;
- on-time delivery;
- QC pass;
- return rate;
- price variance;
- lead time;
- score/trend.

PLAN-005 freezes source candidates but not a composite "score" formula.

Source semantics:
- OTD from promised vs accepted receipt dates;
- QC pass from Quality authority;
- return rate from Purchase Return source quantities;
- price variance from PO vs posted Supplier Invoice comparison;
- lead time from PO/Sent to Receipt dates.

Composite score weighting remains Reporting/Management policy and is not invented here.

## 5. Purchase Report

Financial purchasing basis:
- posted Supplier Invoice lines for recognized supplier payable/spend;
- physical receipt report remains separate.

Do not equate Goods Receipt with financial expense/payable.

Historical reports use document snapshots, not current master text when legal/commercial history matters.

## 6. Returns

Show separately:
- physical returned quantity;
- financial adjusted value;
- physical pending;
- financial pending.

No single "returned" boolean hides two effects.

## 7. Payment context

Supplier Invoice screens/reports may consume Finance-derived:
- payable balance;
- payment state/allocation when PLAN-007 defines it.

Purchasing does not create authoritative paid/open fields.

## 8. FX / currency

Posted Supplier Invoice reports use immutable posted:
- transaction currency;
- FX source/date/rate;
- base-currency values.

Never revalue posted Purchasing history using today's rate unless a separate Finance revaluation report defines that behavior.

## 9. Exclusions

Report contracts explicitly distinguish:
- DRAFT;
- CANCELLED;
- REVERSED;
- cancelled remainder;
- match exceptions;
- direct financial-only invoices.

Projection/cache is rebuildable.
