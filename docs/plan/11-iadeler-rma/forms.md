# Returns / RMA Forms Contract

Status: FROZEN planning contract.

## 1. Worklists
Customer Returns/RMA and Supplier Returns may have separate views but use one coherent return contract.

Columns/filters:
- return/RMA number;
- direction CUSTOMER_RETURN / SUPPLIER_RETURN;
- Party;
- source document/reference;
- Product/Variant;
- authorized / physically processed / physical remaining;
- financially adjusted / financial remaining where applicable;
- lifecycle;
- physical state;
- QC/disposition;
- financial state;
- refund state;
- warehouse;
- age/overdue/exception.

Status is never color-only.

## 2. Detail
Must show separately:
- authorization/case state;
- immutable Party/Product/source snapshots;
- original eligible source quantity;
- authorized quantity;
- physical processed/remaining;
- financial adjusted/remaining;
- refund status/amount;
- lot/serial/UOM;
- warehouse/location/disposition;
- source-target links;
- Inventory movement references;
- Finance posting/valuation references;
- replacement Sales link if any;
- evidence/files;
- timeline/reversal lineage.

## 3. Authorization
Required:
direction, Party/role, company, reason, Product/Variant/UOM, quantity > 0, source or explicit source-less exception basis.
Normal source-linked mode validates source eligibility before authorization.
Source-less mode visibly requires reason/evidence/approval.

## 4. Customer receipt
Shows authorized, prior received and remaining.
Requires warehouse/location and required lot/serial.
Preview states: STOCK IN → QUARANTINE; Customer Account NONE; Cash/Bank NONE.
Over-processing is blocked.

## 5. QC/disposition
Action choices only when eligible:
AVAILABLE, QUALITY_HOLD, REWORK, DAMAGED; SCRAP is a separate explicit downstream action.
Show that disposition does not itself refund/credit customer.

## 6. Financial adjustment
Customer credit preview:
CUSTOMER CREDIT; STOCK NONE; Cash/Bank NONE.

Supplier adjustment preview:
SUPPLIER DEBIT; STOCK NONE; Cash/Bank NONE.

Show currency, basis, eligible remaining, posting date/period, FX snapshot and approval/reason when required.

## 7. Refund
Customer refund and supplier refund launch Finance-owned workflow.
UI must not label a return as cash-refunded merely because a credit/adjustment exists.

## 8. Supplier shipment
Shows current eligible stock custody, source Goods Receipt, lot/serial and physical remaining.
POST preview: STOCK OUT; Supplier Account NONE; Cash/Bank NONE.

## 9. Replacement
Replacement/exchange action creates/navigates to normal Sales workflow with a link back to return case.
No one-click hidden stock/finance effects.

## 10. Bulk/high-volume
Support filters/multi-select for pending receipt, QC, supplier shipment and financial pending.
Each item validates independently; partial failure and retry/result summary are visible.
