# Finance / Treasury Conceptual Data Contract

Status: FROZEN logical/domain planning only. No physical SQL schema, index or column type is defined here.

## 1. Authority model

Finance authoritative:
- Account Ledger;
- Cash Ledger;
- Bank Ledger;
- Finance transaction documents;
- treasury transfer;
- statement evidence/reconciliation links;
- FX carrying/revaluation records;
- credit/risk/hold policy and signal;
- Inventory Valuation / Cost Ledger.

Not authoritative:
- Party mutable balance fields;
- Invoice paid/open fields;
- UI balance caches;
- statement-import rows as Bank book truth;
- dashboard/report projections;
- V38 Open Items allocation state;
- Product average-cost field.

## 2. Account financial role

One Party can hold separate financial roles:
- CUSTOMER_RECEIVABLE;
- SUPPLIER_PAYABLE.

A role is not a duplicate Party identity.

Conceptual role balance dimensions:
- company;
- Party;
- financial role;
- currency.

Customer and Supplier balances are derived separately.
They do not net automatically.

## 3. Account Ledger Entry

Append-only posted financial effect.

Conceptually carries:
- company;
- branch when source requires it;
- Party;
- financial role;
- direction DEBIT/CREDIT;
- transaction currency;
- amount;
- base-currency amount;
- document date;
- posting date;
- due date where relevant;
- source type/id/line;
- transaction/idempotency identity;
- reversal/original link;
- actor/audit/correlation;
- classification such as INVOICE, COLLECTION, PAYMENT, ADVANCE, REFUND, ROLE_NETTING, ADJUSTMENT.

Rules:
- amount uses decimal;
- posted entry immutable;
- reversal is new entry;
- no open-item allocation required.

Derived:
CUSTOMER balance = debit - credit.
SUPPLIER payable = credit - debit.

## 4. Cash Account

Company + branch-scoped money container.

Conceptually:
- code/name;
- currency;
- ACTIVE/INACTIVE;
- policy references;
- permissions/scopes;
- audit.

No mutable current_balance authority.

Currency cannot change after posted history.

## 5. Cash Ledger Entry

Conceptually:
- company/branch;
- Cash Account;
- direction IN/OUT;
- currency/amount/base amount;
- posting/value/document dates as applicable;
- source transaction;
- reversal;
- actor/correlation.

Derived cash balance = IN - OUT.

Opening balance is explicit OPENING movement.

## 6. Bank Account

Company + branch-scoped bank-book identity.

Conceptually:
- Bank identity/name;
- account name/reference;
- IBAN/account reference where applicable;
- fixed currency;
- ACTIVE/INACTIVE;
- optional overdraft policy;
- statement-import configuration reference;
- permission scope.

Bank provider/account IDs remain external mappings and do not replace Mars identity.

## 7. Bank Ledger Entry

Conceptually:
- company/branch;
- Bank Account;
- direction IN/OUT;
- transaction amount/currency;
- base amount;
- posting date;
- value date;
- bank/reference metadata;
- source Finance transaction;
- reversal;
- statement reconciliation state projection;
- actor/correlation.

Derived Bank book balance = IN - OUT.

Statement amount/balance is not copied in as Bank Ledger authority merely because a file was imported.

## 8. Finance Transaction

Planning concept for one business transaction such as:
- COLLECTION;
- PAYMENT;
- CUSTOMER_REFUND;
- SUPPLIER_REFUND;
- CUSTOMER_ADVANCE;
- SUPPLIER_ADVANCE;
- ROLE_NETTING;
- TREASURY_TRANSFER;
- FX_TRANSFER;
- CASH_ADJUSTMENT;
- FX_REVALUATION;
- FINANCIAL_CORRECTION.

Each transaction:
- has state;
- owns exact intended paired ledger effects;
- has company/branch;
- currency/base snapshots;
- source/reason;
- approval evidence where required;
- durable idempotency identity;
- reversal link.

P3 decides whether transaction subtypes use separate bounded-context tables or normalized extensions.

## 9. Role Netting

Explicit relationship between same Party's two financial roles.

Conceptually:
- Party/company;
- currency;
- amount;
- CUSTOMER role movement;
- SUPPLIER role movement;
- reason;
- creator/approver;
- posting date;
- reversal.

No Cash/Bank.
No Invoice allocation.

## 10. Advance position

Advance/prepayment is not a separate mutable balance field.

Customer advance:
- CUSTOMER role net credit position from posted Account Ledger entries.

Supplier advance:
- SUPPLIER role net debit position.

Transaction classification enables reporting and refund eligibility.

## 11. FX carrying bucket

For each relevant foreign-currency monetary position, Finance derives:
- nominal foreign-currency balance;
- base carrying value;
- weighted carrying rate;
- sign/position side;
- realized FX history;
- unrealized revaluation adjustments.

Possible dimensions by family:
- Party + role + currency;
- Cash Account + currency;
- Bank Account + currency.

Because Cash/Bank account currency is fixed, currency remains explicit for consistency/history.

When a transaction crosses zero, carrying calculation splits settled old-sign amount from new opposite-sign position.

## 12. Treasury Transfer

Conceptually:
- company/branch;
- source money account type/id;
- target money account type/id;
- source amount/currency;
- target amount/currency;
- same-currency or FX mode;
- executed/reference FX data;
- fees;
- posting/value dates;
- approval;
- paired Cash/Bank Ledger entry references;
- realized FX entry where applicable;
- reversal.

Source and target cannot represent the same logical account/direction no-op.

## 13. Bank Statement Import

Statement import batch:
- Bank Account;
- source/provider/format;
- file/evidence/checksum;
- imported-at;
- status;
- actor.

Statement line:
- stable external transaction ID when provided;
- transaction/value date;
- description/reference;
- direction;
- amount/currency;
- source fingerprint;
- duplicate/suspected duplicate state;
- reconciliation state.

Statement line is evidence/staging, not money authority.

## 14. Reconciliation Match

Explicit relationship between:
- one/more statement lines;
- one/more posted Bank Ledger entries;
- matched amount;
- currency;
- actor/time;
- status/reason.

Many-to-many is conceptually permitted.

Invariants:
- matched amount > 0;
- cannot exceed statement-line unmatched remainder;
- cannot exceed Bank-entry unreconciled remainder;
- currency/account compatibility required;
- reversed Bank entry invalidates/reopens affected reconciliation evidence for review.

## 15. Credit / Risk Policy

Finance-owned company + customer-role policy conceptually includes:
- credit limit;
- manual hold;
- enabled/disabled risk controls;
- Sales exposure inclusion rule/version;
- effective dates;
- approval/audit.

Risk Projection consumes:
- positive customer receivable exposure;
- Sales confirmed/uninvoiced exposure;
- eligible customer credit;
- overdue projection.

Projection can be rebuilt.
Manual hold/policy are authoritative Finance controls.

## 16. Aging Projection

Derived only.

Conceptual segments originate from Account Ledger due-dated positive role balances.

Opposite-direction posted movements are applied FIFO to oldest eligible segments for reporting.

No authoritative relation is written back from projection to Collection/Payment/Invoice.

Projection may expose:
- not due;
- 0-30;
- 31-60;
- 61-90;
- 90+;
- overdue total.

Exact bucket boundaries are reporting policy and may be configured; source dates and formula remain explicit.

## 17. Posting Period

Conceptually:
- company;
- start/end;
- state OPEN/FROZEN/CLOSED;
- module gates;
- override evidence;
- audit.

Posting date maps a financial effect to period.

Period state never changes source document date.

## 18. Inventory Valuation Pool

Authoritative financial valuation concept.

Grain:
- company;
- Product or stock-relevant Variant;
- Base UOM;
- company base currency.

Derived/current state from valuation ledger:
- on-hand valued qty;
- carrying value;
- moving weighted average;
- dispatched-not-invoiced bridge value;
- late-cost pending/reconciled value.

Warehouse/Location is not default valuation pool dimension.

## 19. Inventory Valuation Entry

Append-oriented financial/cost effect linked to physical/source event.

Kinds conceptually:
- RECEIPT_PROVISIONAL_VALUE;
- RECEIPT_LATE_COST_ADJUSTMENT;
- LANDED_COST_ADJUSTMENT;
- DISPATCH_VALUE_OUT;
- DISPATCH_COST_BRIDGE_IN;
- COGS_RECOGNITION;
- COGS_ADJUSTMENT;
- COUNT_ADJUSTMENT_VALUE;
- SCRAP_WRITE_OFF;
- PURCHASE_RETURN_VALUE_OUT;
- REVERSAL.

Carries:
- source document/movement;
- Product/Variant;
- quantity basis;
- transaction/base value;
- prior/new moving average evidence where needed;
- posting date;
- original/reversal link.

Quantity truth remains Inventory Ledger.
Value truth remains Finance valuation authority.

## 20. Moving weighted average

For an inbound value increase with positive quantity:

new carrying value =
prior carrying value + inbound eligible base value.

new valued quantity =
prior valued quantity + inbound quantity.

new moving average =
new carrying value / new valued quantity.

Late cost with no quantity changes carrying value and therefore moving average of remaining pool.

Outbound:
- uses current moving average at posting;
- freezes exact outbound valuation basis.

Internal Warehouse transfer:
- no valuation pool quantity/value change.

P3 must preserve decimal precision and deterministic rounding without floating point.

## 21. Dispatch cost bridge

Required because physical stock-out and financial COGS recognition occur at different events.

Dispatch POST:
- reduces inventory valuation pool;
- creates source-linked dispatched-not-invoiced cost bridge.

Sales Invoice POST:
- consumes eligible bridge value into recognized COGS.

Invoice reversal:
- reverses COGS recognition back to bridge or appropriate correction state without silently restoring physical stock.

Dispatch reversal:
- physical/valuation reversal depends on whether bridge/COGS has downstream financial recognition; compensating workflow must preserve consistency.

## 22. Late cost allocation

Supplier Invoice price delta / landed cost keeps Goods Receipt source lineage.

Eligible cost delta is distributed to source-receipt quantity portions:
- still on hand → Inventory Valuation pool;
- physically dispatched but not financially invoiced → Dispatch cost bridge;
- already COGS-recognized → COGS adjustment.

The split is derived from authoritative source-target quantity/cost lineage, not a manually edited Product average cost.

## 23. Positive Count Adjustment valuation

If pool has valid positive valued quantity:
- use current moving average.

If no valid moving average:
- Finance valuation input is required;
- actor permission + reason + approval;
- value must be explicit.

Silent zero-cost positive count is forbidden.

## 24. Scrap/write-off valuation

Warehouse owns physical scrap STOCK OUT.

Finance owns:
- inventory carrying-value removal at accepted moving-average basis;
- write-off/cost classification;
- financial reversal.

No Cash/Bank or Party account effect unless another explicit business event exists.

## 25. Idempotency / concurrency intent

Future durable guarantees must protect:
- duplicate Invoice Finance effect;
- duplicate Collection/Payment/Refund POST;
- concurrent Cash/Bank spend;
- transfer double-post;
- FX transfer retry;
- statement duplicate import;
- duplicate statement-derived transaction;
- reconciliation overmatch;
- duplicate revaluation;
- duplicate late-cost allocation;
- Dispatch cost bridge double consumption;
- count/scrap valuation double-post;
- reversal retry.

Exact DB constraint/locking strategy belongs P3.

## 26. Forbidden data models

- customer.current_balance authority;
- supplier.current_balance authority;
- invoice.paid/open_balance authority;
- payment-to-invoice allocation authority;
- open-item table treated as source of truth;
- CashAccount.current_balance authority;
- BankAccount.current_balance authority;
- imported statement row directly treated as Bank Ledger;
- Product.average_cost authoritative mutable field;
- silent financial ledger update/delete;
- float/double for money/FX/cost;
- comma-separated reconciliation/source IDs.
