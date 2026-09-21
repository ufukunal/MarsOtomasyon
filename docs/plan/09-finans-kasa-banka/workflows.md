# Finance / Treasury Workflows

Status: FROZEN — PLAN-007

## 1. Account Ledger recognition

Sales Invoice POST:
CUSTOMER role DEBIT.

Collection POST:
CUSTOMER role CREDIT + Cash/Bank IN.

Supplier Invoice POST:
SUPPLIER role CREDIT.

Payment POST:
SUPPLIER role DEBIT + Cash/Bank OUT.

Refund and role-netting use explicit compensating directions.

No workflow creates authoritative Invoice settlement/open-item state.

## 2. Collection

DRAFT → POSTED → REVERSED.
Draft may CANCEL.

Required:
- company/branch;
- active CUSTOMER Party role;
- amount > 0;
- currency;
- Cash or Bank account;
- posting date;
- value date where relevant;
- source/reason.

POST atomically:
- Account Ledger CUSTOMER CREDIT;
- Cash Ledger IN or Bank Ledger IN;
- audit/outbox.

If amount creates a customer credit beyond current receivable:
- classify excess as Customer Advance;
- permission/approval;
- no invoice allocation.

## 3. Payment

DRAFT → PENDING_APPROVAL when policy requires → POSTED → REVERSED.

Required:
- active SUPPLIER Party role;
- Cash/Bank source;
- amount/currency;
- posting date;
- source/reason.

POST atomically:
- SUPPLIER DEBIT;
- Cash/Bank OUT;
- realized FX if applicable.

If amount exceeds payable:
- excess is Supplier Advance only through explicit prepayment path.

## 4. Customer / Supplier advance

Customer Advance:
Cash/Bank IN + CUSTOMER CREDIT position.

Supplier Advance:
Cash/Bank OUT + SUPPLIER DEBIT position.

Future invoice changes aggregate role balance but does not create authoritative allocation.

Return/refund path consumes only eligible credit/advance cap.

## 5. Explicit role netting

For same Party holding CUSTOMER + SUPPLIER:

1. load separate role balances;
2. validate same company/currency;
3. calculate max eligible amount;
4. enter amount/reason;
5. approval required;
6. POST CUSTOMER CREDIT + SUPPLIER DEBIT atomically.

Cash/Bank effect = NONE.

No source invoices are marked paid.

## 6. Customer Refund

Eligibility:
- customer credit/advance and/or approved Returns/RMA financial entitlement.

POST:
- CUSTOMER DEBIT;
- Cash/Bank OUT;
- eligible cap decreases.

Cannot refund beyond cap.

## 7. Supplier Refund received

Eligibility:
- supplier advance/credit entitlement.

POST:
- SUPPLIER CREDIT;
- Cash/Bank IN.

Cannot receive refund beyond eligible cap without another valid source transaction.

## 8. Cash Account lifecycle

ACTIVE:
- allowed for authorized new transactions.

INACTIVE:
- read/history only.

Currency fixed after first posted movement.

Opening amount:
- explicit OPENING Cash Ledger IN/OUT transaction.

Cash cannot go negative under normal commands.

Deactivation requires zero derived balance and resolved pending work/count/transfers.

## 9. Bank Account lifecycle

ACTIVE / INACTIVE.

Currency fixed after posted history.

Opening balance:
explicit Bank Ledger OPENING transaction.

Normal source-funds validation:
- negative bank book balance blocked unless active account-specific overdraft policy explicitly allows it.

Deactivation:
- zero book balance;
- no pending transfer;
- no unresolved reconciliation/import blocker defined by policy.

## 10. Same-currency transfer

One Transfer document:
DRAFT → PENDING_APPROVAL when required → POSTED → REVERSED.

Source may be Cash or Bank.
Target may be Cash or Bank.

POST atomically:
- source OUT;
- target IN;
- exact same transaction-currency amount;
- explicit transfer fee separately if any.

No Party Account effect.

## 11. FX Transfer

For different currencies:

Required:
- source amount/currency;
- target amount/currency;
- actual transaction rate/source;
- base values;
- value/posting dates;
- fee/spread data;
- approval when overridden/outside Treasury Policy.

POST:
- source Cash/Bank OUT;
- target Cash/Bank IN;
- realized FX entry if source foreign-currency carrying value differs from executed base value;
- no hidden balancing.

## 12. Realized FX

A reducing settlement/conversion uses weighted-average carrying base rate of the same-sign currency position.

Process:
1. determine foreign-currency amount reducing existing position;
2. derive proportional carrying base value;
3. calculate transaction/settlement base value;
4. difference becomes realized FX gain/loss;
5. if transaction crosses zero, excess establishes new opposite-sign position at current transaction rate.

Applies to:
- customer Collection;
- supplier Payment;
- Refund;
- FX conversion;
- other accepted monetary settlement.

## 13. Period-end revaluation

Select company + open foreign-currency monetary buckets.

Load:
- nominal currency balance;
- carrying base value;
- approved Finance FX Revaluation Policy;
- period-end rate.

Preview:
- revalued base value;
- unrealized difference.

POST:
- separate unrealized revaluation adjustment;
- original nominal ledger entries unchanged.

Later reversal/revaluation maintains lineage.

Missing required policy/rate blocks POST.

## 14. Statement import

Steps:
1. select Bank Account/source format;
2. parse/validate;
3. preview;
4. dedupe by stable external transaction ID where available;
5. fingerprint probable duplicates where not;
6. import statement evidence;
7. proceed to reconciliation.

Import creates no Bank Ledger movement.

## 15. Reconciliation

For each statement line:
- search posted Bank Ledger candidates by account, amount, currency, date/value-date window, reference;
- present suggestions/confidence;
- operator may match existing;
- operator may create proposed Finance transaction;
- operator may split/aggregate explicit matched amounts.

POST/confirm reconciliation:
- only links evidence to already-posted Bank Ledger movement(s);
- cannot mutate ledger amount/source;
- matched totals cannot exceed eligible statement/ledger remainder.

Unmatched line stays UNMATCHED.

Ignored line requires reason/permission.

## 16. Cash Count

DRAFT → COUNTING → REVIEW → PENDING_APPROVAL if discrepancy → POSTED.

Start:
Cash Ledger expected snapshot.

Count:
physical cash count.

Review:
difference = counted - ledger expected.

Zero:
close with no adjustment.

Non-zero:
approval + CASH_ADJUSTMENT.

Never set cash balance directly.

## 17. Credit / risk / hold

Finance projection consumes:
- current CUSTOMER receivable;
- Sales confirmed/uninvoiced exposure;
- eligible customer credit/advance.

Exposure:
max(0, receivable + unbilled order exposure - eligible credits).

Finance owns:
- credit limit;
- manual hold;
- policy-derived hold;
- risk signal.

Sales consumes this signal.

## 18. Aging

Aging is report-only:
- role + currency buckets;
- due-dated positive balance segments;
- opposite-direction movements reduce oldest eligible segments FIFO in projection.

No authoritative allocation relation is persisted.
No Invoice status is changed.

## 19. Inventory valuation

### Goods Receipt

Physical quantity:
Inventory Ledger IN.

Finance valuation:
- provisional receipt base value enters Product/Variant company valuation pool;
- moving average recalculated.

Payable remains Supplier Invoice-owned.

### Sales Dispatch

Physical quantity OUT.
Valuation pool reduces at current moving average.
Dispatch cost basis frozen into dispatched-not-invoiced bridge.

No COGS yet.

### Sales Invoice

Recognize COGS from eligible dispatch cost basis.
Do not reduce inventory value again.

### Supplier Invoice / late cost

Compare accepted invoice cost to source receipt provisional value.

Delta follows source receipt lineage:
- on-hand share → inventory carrying value;
- dispatched-not-invoiced share → bridge;
- already invoiced/recognized share → COGS adjustment.

No stock quantity effect.

### Count

Negative adjustment:
current moving-average value leaves inventory and becomes approved count/write-off cost.

Positive:
current average if valid pool exists; otherwise explicit approved unit valuation required.

### Scrap

Warehouse posts physical OUT.
Finance removes current moving-average carrying value and recognizes write-off cost.

### Transfer

Warehouse transfer changes no valuation pool amount/value because pool is company + Product/Variant based.

## 20. Posting period

OPEN:
normal posting.

FROZEN:
normal post blocked; authorized override requires reason + approval.

CLOSED:
posting blocked.
Historical correction posts linked reversal/correction in eligible open period.
Reopen is separate high-risk Finance/Settings workflow.

## 21. Reversal

For any posted financial transaction:
- verify source/dependent/reconciliation state;
- create exact compensating Account/Cash/Bank/Cost effects;
- link original;
- reason/audit;
- keep original immutable.

Statement reconciliation referencing reversed Bank movement becomes reconciliation exception/review; it is not silently redirected.

## 22. Idempotency

POST commands carry durable logical identity.
Retry of same logical POST returns existing result / conflicts safely.
Duplicate financial effect is forbidden.

Provider/import network retry cannot bypass DB idempotency.
