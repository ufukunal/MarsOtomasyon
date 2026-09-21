# Ledgers and Finance Logical Model

Status: FROZEN — PLAN-010.

## 1. Ledger separation
Use separate logical ledger families because invariants differ:
- Inventory Movement — physical quantity;
- Account Ledger Entry — Party financial balance;
- Cash Ledger Entry — cash book;
- Bank Ledger Entry — bank book;
- Inventory Valuation Entry — inventory/cost value.

A single universal ledger is rejected.

## 2. Inventory Movement
Required logical dimensions where applicable:
company, Product/Variant, entered UOM, base quantity, movement kind/direction, source/target Warehouse, Location, disposition, Lot, Serial, source document/line/work, posting time, actor, correlation/idempotency, original/reversal link.

Rules:
- posted immutable;
- quantity decimal;
- serial unit semantics;
- source/target scope compatible;
- no direct current_stock update.

## 3. Account Ledger
Dimensions:
company, applicable branch, Party, financial role, DEBIT/CREDIT, currency, amount, base amount, document/posting/due date, classification, source transaction/document/line, actor/correlation/idempotency, reversal.

Derived:
- CUSTOMER balance = debit - credit;
- SUPPLIER payable = credit - debit.

No Invoice allocation/open-item relation.

## 4. Cash / Bank
Cash/Bank Account owns identity, company/branch, fixed currency after posting and lifecycle.
Ledger entries own IN/OUT amount/base value, dates, source Finance Transaction and reversal.
Current balances are derived.

Bank Statement rows remain staging/evidence.

## 5. Finance Transaction
Normalized Finance transaction header coordinates one accepted business event:
Collection, Payment, Customer/Supplier Advance, Refund, Role Netting, Treasury/FX Transfer, Adjustment, Revaluation, Financial Correction.

Stores logical:
company/branch, kind, state, currency/base snapshots, dates, source/reason, approval, idempotency, original/reversal.

Subtype-specific details remain normalized rather than one wide nullable finance table.

## 6. Atomic effect contracts
- Collection: CUSTOMER CREDIT + Cash/Bank IN.
- Payment: SUPPLIER DEBIT + Cash/Bank OUT.
- Customer Refund: CUSTOMER DEBIT + Cash/Bank OUT.
- Supplier Refund: SUPPLIER CREDIT + Cash/Bank IN.
- Role Netting: CUSTOMER CREDIT + SUPPLIER DEBIT, no Cash/Bank.
- Same-currency Transfer: source OUT + target IN.
- FX Transfer: source OUT + target IN + realized FX/value effects where applicable.

All component effects of one POST are one PostgreSQL transaction.

## 7. Posting periods and dates
Keep document_date, posting_date, value_date and audit timestamp semantically separate.
Posting Period gates Finance posting by company/date.
FROZEN override and CLOSED correction preserve reason/approval; source dates are not silently changed.

## 8. FX
Preserve transaction currency, base currency/value and rate source/date snapshots.
Weighted carrying basis belongs Finance for reducing foreign-currency positions.
Unrealized revaluation is separate from original ledger entry.
Original commercial FX snapshots remain immutable.

## 9. Valuation
Valuation pool grain:
company + Product/stock-relevant Variant + Base UOM + base currency.

Inventory Valuation Entry kinds support:
receipt provisional value, late/landed cost, dispatch value out, dispatch bridge in/consume, COGS, count adjustment value, scrap write-off, purchase return value out, customer-return correction, reversal.

Physical quantity remains Inventory Ledger authority.

## 10. Dispatch Cost Bridge
Dispatch POST:
- Inventory quantity OUT;
- valuation pool value OUT at current moving average;
- source-linked bridge amount/quantity becomes eligible for later COGS.

Sales Invoice:
- consumes eligible bridge value into COGS;
- no second Inventory quantity/value out.

Bridge consumption is normalized by source Dispatch portion and Invoice line/finance effect so duplicate/over-consumption can be protected.

## 11. Returns valuation
Supplier physical return uses current moving-average outbound valuation and source Return/Goods Receipt lineage.
Customer return valuation/cost correction references original Sales Dispatch/Invoice/cost lineage when available.
Source-less positive return without valid basis requires explicit approved valuation input; silent zero-cost value is forbidden.

## 12. Reconciliation
Normalized match details carry matched amount.
Many-to-many permitted.
Bank Ledger remains immutable.
Reversal of matched Bank entry causes reconciliation review/exception, not silent remap.

## 13. Instruments
Checks/Notes financial receivable/payable positions remain Finance-owned records/effects linked to Instrument Movement.
They are not mixed into Party Account balance or Cash/Bank before the frozen recognition events.
Custody stays Checks/Notes-owned.

## 14. Forbidden finance models
- mutable Party/Cash/Bank balance authority;
- Invoice paid/open_balance;
- payment/collection-to-Invoice allocation authority;
- imported statement = Bank Ledger;
- Product.average_cost authority;
- float/double accounting truth;
- silent update/delete of posted ledger.
