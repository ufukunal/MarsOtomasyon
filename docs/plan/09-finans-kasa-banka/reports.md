# Finance / Treasury Reporting Contract

Status: FROZEN PLAN-007 reporting/read-projection semantics.

Reports are not financial authority.

## 1. Party Balance List

Grain:
Party × financial role × currency.

Customer:
balance = Account Ledger debit - credit.

Supplier:
payable = Account Ledger credit - debit.

Show role separately.
Do not auto-net Customer and Supplier balances.

Optional total informational net position must be labelled derived only.

## 2. Detailed Party Statement

Grain:
Account Ledger entry.

Fields:
- posting date;
- document/source;
- role;
- debit/credit;
- transaction currency/amount;
- base amount;
- due date;
- running role/currency balance;
- reversal/original;
- project/reference/description.

Source:
Account Ledger only.

## 3. Aging

Grain:
Party × role × currency × aging bucket.

Derived from due-dated ledger balance segments.

Reporting projection uses FIFO consumption of oldest eligible balance segments by opposite-direction entries.

This is not:
- Invoice paid/open state;
- Collection allocation;
- Payment allocation.

Aging buckets must expose configured boundaries.
Default presentation may use:
- Not Due
- 0-30
- 31-60
- 61-90
- 90+

But report metadata must state actual bucket policy.

## 4. Credit / Risk

Per CUSTOMER role:

Inputs:
- current positive receivable exposure;
- overdue projection;
- Sales confirmed/uninvoiced commercial exposure;
- eligible customer credit/advance;
- configured credit limit;
- manual/policy hold.

Core:
exposure = max(0, receivable + unbilled exposure - eligible credits).

remaining_limit = credit_limit - exposure.

If no credit limit is configured, report shows UNLIMITED/NOT_CONFIGURED explicitly rather than fake numeric infinity.

Finance risk signal is authoritative for hold/limit configuration; report is projection.

## 5. Cash Book

Grain:
Cash Ledger entry.

Measures:
- IN;
- OUT;
- running balance;
- transaction/base currency;
- source;
- reversal.

Current balance derived from ledger.

## 6. Cash Count

Per Cash Count:
- ledger expected;
- counted;
- discrepancy;
- adjustment;
- approver;
- post state.

No report reads mutable CashAccount balance.

## 7. Bank Book

Grain:
Bank Ledger entry.

Fields:
- posting/value date;
- Bank Account;
- IN/OUT;
- currency/base value;
- reference;
- source;
- running book balance;
- statement/reconciliation context;
- reversal.

Book balance derives from Bank Ledger.

## 8. Bank Statement / Reconciliation

Statement report:
- imported statement evidence;
- external ID/reference;
- amount/direction;
- transaction/value dates;
- duplicate status;
- reconciliation state.

Reconciliation report:
- statement amount;
- matched amount;
- remaining;
- matched Bank entries;
- exception/ignore reason.

Book vs statement difference:
statement evidence balance - Bank Ledger book balance at aligned date/currency.

It never auto-posts an adjustment.

## 9. Cash Flow

Actual cash-flow report uses posted Cash + Bank Ledger.

Classify by source transaction:
- Collection;
- Payment;
- Refund;
- treasury transfer;
- expense;
- advance;
- other accepted Finance source.

Internal Cash/Bank transfers are excluded from company net external cash flow to avoid double counting.

FX transfer:
- source/target legs are internal treasury movement;
- realized FX/fees reported separately.

Forecast cash flow is a separate later projection from due/commitment data and is not actual ledger truth.

## 10. FX Position

Grain:
company × monetary family/account/role × currency.

Show:
- nominal foreign-currency balance;
- carrying base value;
- weighted carrying rate;
- current/reference rate;
- unrealized delta;
- realized FX period amount;
- last revaluation.

Source:
Finance ledger + FX carrying/revaluation authority.

## 11. Treasury Transfer Report

Per transfer:
- source/target;
- source amount/currency;
- target amount/currency;
- actual/reference rate;
- fees;
- realized FX;
- posting/value date;
- state/reversal.

Same-currency internal transfers do not inflate cash-flow totals.

## 12. Inventory Cost / Valuation

V38-aligned Product/Variant valuation report.

Grain:
company × Product/Variant valuation pool.

Show:
- valued on-hand qty;
- carrying value;
- moving weighted average;
- last receipt;
- late cost pending;
- dispatched-not-invoiced bridge;
- recognized COGS;
- valuation mismatch flags.

Sources:
- Inventory Ledger for physical quantity;
- Finance Valuation Ledger for carrying value;
- source Goods Receipt/Dispatch/Invoice/Count/Scrap lineage.

No Product master current-cost authority.

## 13. Gross Margin

Where sufficient source data exists:

recognized revenue:
posted Sales Invoice net revenue according to Sales calculation snapshot.

recognized COGS:
Finance COGS entries recognized at Sales Invoice POST.

gross margin =
revenue - COGS.

Margin report must preserve currency/base rules and reversal status.

Do not calculate authoritative COGS from current Product average cost.

## 14. Count / Scrap Cost

Count report:
- physical discrepancy qty;
- valuation basis;
- carrying-value effect;
- manual valuation approval when used.

Scrap report:
- physical quantity source;
- moving-average carrying value removed;
- write-off classification/value;
- approval/reversal.

Warehouse quantity source and Finance value source are both referenced.

## 15. Advances

Customer:
- customer credit/advance role position;
- origin transaction classification;
- refunded amount;
- current aggregate eligible credit.

Supplier:
- supplier debit/advance position;
- origin transaction;
- refunds/offsetting aggregate balance.

No invoice application column is authoritative.

## 16. Refunds

Show:
- Party;
- role;
- source entitlement;
- eligible cap;
- posted/refunded;
- remaining cap;
- channel Cash/Bank;
- reversal.

Physical return state is a linked Returns/Warehouse projection, not Finance stock authority.

## 17. Period / Control reports

Show:
- period state;
- module gate;
- override count;
- overrides with actor/reason/approver;
- postings/reversals into period;
- reopened/closed timeline.

## 18. Excluded statuses

Financial balance reports include only posted ledger effects net valid reversal entries.

DRAFT/CANCELLED transaction documents:
- no balance effect.

Reversal:
- original remains visible;
- net balance includes compensating entry.

Statement-only evidence:
- excluded from Bank book money totals.

## 19. Security / exports

Every report applies:
- company scope;
- branch/money-account permission;
- Finance read permission;
- sensitive Bank/IBAN masking.

Export obeys same filters/security as screen.

Projection/cache can be rebuilt and never authorizes mutation.
