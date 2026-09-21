# Finance / Treasury UI / Form Contract

Status: FROZEN — PLAN-007

## 1. Personas

- finance operator;
- cashier;
- treasury operator;
- finance manager/approver;
- reconciliation operator;
- accounting reviewer.

Desktop is keyboard/dense-grid oriented.
High-risk actions show exact ledger/balance effect before POST.

## 2. Party Balance List

V38 Bakiye Listesi is adapted.

Show separately:
- Customer Receivable role balance;
- Supplier Payable role balance;
- currency;
- overdue aging projection;
- credit limit/exposure/hold.

Do not collapse dual-role Party into one automatically netted number by default.

Optional combined informational net position may be shown only as derived analytics and never as posting authority.

## 3. Detailed Party Statement

V38 Detaylı Cari Ekstre is KEEP/ADAPT.

Columns:
- posting date/time;
- document/reference;
- source;
- role;
- debit;
- credit;
- transaction currency;
- base amount;
- due date;
- running role/currency balance;
- reversal link;
- description.

Actions:
- Go to Source
- Reverse/Correction workflow where authorized
- explicit Role Netting when eligible

No action creates Invoice paid/open state.

## 4. Open Items migration behavior

V38 Açık Kalemler and invoice-distribution settlement UI are REMOVE AS AUTHORITY.

If a future screen keeps the name for continuity, it must be read-only derived Aging/Balance Segments and labelled:
"Projection — not invoice settlement authority."

No:
- Partially Paid Invoice authority;
- Collection allocation;
- Supplier Payment allocation;
- editable open balance.

## 5. Collection form

V38-aligned fields:
- Branch
- Party
- transaction type
- amount
- currency
- Cash/Bank account
- posting date/time
- value date when relevant
- document/reference number
- note

Tabs:
- General
- Balance Effect
- Money Effect
- FX
- Receipt
- Timeline

Before POST show:
- CUSTOMER role balance before/after;
- Cash/Bank before/after projection;
- advance portion if amount crosses into credit;
- realized FX if applicable.

## 6. Payment form

Fields analogous to Collection.

Before POST show:
- supplier payable before/after;
- Cash/Bank source before/after;
- Supplier Advance portion if overpayment;
- FX effect;
- approval.

No Invoice allocation tab.

## 7. Refund

List:
- Refund No
- Party
- source entitlement
- eligible amount
- refunded
- remaining cap
- Cash/Bank channel
- state.

Detail:
- Eligibility
- Money Effect
- Party Balance Effect
- FX
- Approval
- Timeline

POST and Reverse are distinct actions.

## 8. Cash Accounts

V38 Kasalar KEEP.

List:
- Cash Code
- Name
- Branch
- Currency
- Opening transaction amount
- IN
- OUT
- derived Balance
- Last Count
- State.

Detail:
- General
- Movements
- Count
- Permissions
- Timeline.

Balance is read-only projection.

## 9. Cash Count

V38 flow:
Start Count → Review → Approve → Post.

Show:
- ledger expected;
- physical counted;
- difference;
- recount if any;
- approval;
- posted CASH_ADJUSTMENT.

No "Set Balance" action.

## 10. Bank Accounts

V38 Bank Account view KEEP.

List:
- Bank
- Account
- IBAN
- Branch
- Currency
- Book Balance
- Statement Balance
- Difference
- Last Import
- State.

Important:
Book Balance = Bank Ledger.
Statement Balance = imported evidence.
Difference never auto-adjusts book.

## 11. Bank Movements

Show:
- posting date;
- value date;
- Bank;
- reference;
- description;
- IN / OUT;
- book running balance;
- statement link;
- reconciliation state;
- source/reversal.

Provider statement "Borç/Alacak" labels must be normalized to Mars IN/OUT meaning to avoid bank-perspective ambiguity.

## 12. Treasury / FX Transfer

Tabs:
- General
- Source/Target
- FX
- Fees
- Movements Preview
- Approval
- Timeline

Same currency:
- source OUT;
- target IN;
- same amount.

FX:
- source amount/currency;
- target amount/currency;
- actual rate;
- reference rate;
- base carrying value;
- realized FX;
- fees.

POST preview must balance explicit effects.

## 13. Statement Import

V38 workflow:
File → Parse → Preview → Import → Reconcile.

Preview:
- date/value date;
- reference;
- description;
- IN/OUT;
- fingerprint;
- stable external ID;
- duplicate state;
- validation.

Import does not show "Bank balance updated" because ledger is unchanged.

## 14. Bank Reconciliation

V38 workbench KEEP/ADAPT.

Left/grid:
- statement date;
- description;
- amount;
- reference;
- matched amount;
- remaining;
- suggestion/confidence;
- state.

Inspector:
- candidate Bank Ledger movement(s);
- existing movement match;
- create proposed transaction;
- split/aggregate;
- ignore with reason.

Suggestion % never auto-confirms.

## 15. Advances

List:
- Advance No
- Party/person
- role/type
- date
- currency
- original amount
- consumed by aggregate balance effect
- refunded
- current credit/advance projection
- state.

Do not show "applied invoice" as authority.

## 16. Risk / Credit

V38 Risk / Kredi Limitleri ADAPT.

Show:
- Party;
- credit limit;
- current receivable exposure;
- overdue projection;
- confirmed/uninvoiced Sales exposure;
- eligible credits;
- total exposure;
- remaining limit;
- hold;
- policy/source.

Finance manager edits limit/hold, not Party identity form.

## 17. Inventory Cost

V38 Stok Maliyeti is ADAPT and source-aligned.

Columns/KPIs:
- Product/Variant;
- valuation pool;
- quantity;
- carrying value;
- Moving Avg;
- last receipt;
- late cost pending;
- mismatch/reconciliation state.

Drill-down:
- Goods Receipt valuation input;
- late Supplier Invoice/landed cost;
- Dispatch valuation;
- COGS bridge;
- Count/Scrap adjustments.

No editable Product average-cost field.

## 18. Posting Periods

List:
- period;
- start/end;
- OPEN/FROZEN/CLOSED;
- module gates;
- override count.

Actions:
- Freeze
- Close
- high-risk Reopen where permitted.

Transaction forms display period state before POST.

## 19. Error/Conflict UX

Explicit:
- closed/frozen period;
- duplicate POST;
- insufficient cash/bank funds;
- missing overdraft policy;
- invalid Party role;
- over-advance without permission;
- stale role-netting balance;
- FX rate missing/override approval;
- statement duplicate;
- reconciliation amount mismatch;
- reversed matched Bank movement;
- valuation pool missing/manual cost required.

Draft data should be preserved after recoverable error.
