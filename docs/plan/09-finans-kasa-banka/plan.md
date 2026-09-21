# Finance / Treasury Domain Plan

Status: COMPLETED / FROZEN — PLAN-007

## 1. Business objective

Create one deterministic financial authority in which receivable, payable, cash, bank, FX, reconciliation and inventory valuation can be traced without mutable balance fields, invoice-allocation ambiguity, double posting or silent history edits.

## 2. Sources

SOURCE:
- repository governance and planning standard;
- DB ledger/snapshot principles;
- frozen Sales, Party, Product/Inventory, Purchasing and Warehouse plans;
- Accounting/Finance + ERP primary skill contracts;
- DB, Architecture, Test, Security, Developer and UX reviewer contracts;
- V38 repository Finance/Treasury screens and renderers.

OWNER-AUTHORIZED INFERENCE:
- current owner instructed routine domain decisions to be made quickly from repository + skills + V38 without unnecessary external research.
- decisions below are Mars project policy unless later changed by explicit owner instruction.

No external internet research was required for PLAN-007.

## 3. Scope

In:
- Account/Cash/Bank ledger direction;
- Collection / Payment / Refund;
- advances/prepayments;
- role netting;
- Cash/Bank transfers;
- FX conversion and realized/unrealized FX boundary;
- Bank statement import/reconciliation;
- risk/credit/hold;
- posting periods/dates;
- inventory moving-average valuation;
- count/scrap financial valuation;
- permissions/SoD;
- reports and Full Test Day backlog.

Out:
- physical SQL;
- general ledger/chart-of-accounts implementation;
- tax-return/legal reporting;
- checks/notes;
- Returns/RMA detail;
- application/API/UI code.

## 4. Frozen decisions

### FIN-D001 — Account Ledger authority and role separation

Account Ledger is authoritative Party financial-balance truth.

Every Party account movement is scoped by:
- company;
- Party;
- financial role: CUSTOMER_RECEIVABLE or SUPPLIER_PAYABLE;
- currency;
- source document/transaction;
- posting date;
- debit/credit direction;
- transaction amount and base-currency amount;
- original/reversal lineage.

Customer role:
- DEBIT increases customer receivable.
- CREDIT decreases customer receivable / creates customer credit when it crosses below zero.

Supplier role:
- CREDIT increases supplier payable.
- DEBIT decreases supplier payable / creates supplier advance when it crosses below zero.

Derived projections:
- customer role balance = debit - credit.
- supplier role payable balance = credit - debit.

The two role balances are not automatically netted even for one Party.

### FIN-D002 — Sales Invoice and Supplier Invoice

Sales Invoice POST:
- CUSTOMER_RECEIVABLE DEBIT.
- Cash/Bank = NONE.
- physical STOCK = NONE.
- financial COGS per FIN-D018.

Supplier Invoice POST:
- SUPPLIER_PAYABLE CREDIT.
- Cash/Bank = NONE.
- physical STOCK = NONE.
- inventory late-cost/variance consequences per FIN-D018.

Invoice reversal creates compensating account/cost entries and preserves original history.

### FIN-D003 — Collection

Collection states:
DRAFT → POSTED → REVERSED.
Draft may CANCEL.

POST:
- CUSTOMER_RECEIVABLE CREDIT.
- selected Cash or Bank = IN.
- STOCK = NONE.
- no authoritative Invoice allocation.

Collection is balance-only by explicit frozen Sales policy.

If Collection would create/exceed a customer credit position:
- user must explicitly classify the excess as CUSTOMER_ADVANCE;
- advance permission/reason/approval applies;
- excess is still customer-role Account Ledger credit, not invoice allocation.

### FIN-D004 — Payment

Payment states:
DRAFT → PENDING_APPROVAL when required → POSTED → REVERSED.

POST:
- SUPPLIER_PAYABLE DEBIT.
- selected Cash or Bank = OUT.
- STOCK = NONE.
- no authoritative Supplier Invoice allocation.

PLAN-007 intentionally adopts the same balance-only principle for supplier Payment to avoid a second settlement truth.

If Payment exceeds current supplier payable:
- excess must be explicitly classified SUPPLIER_ADVANCE / PREPAYMENT;
- permission/reason/approval required.

### FIN-D005 — No authoritative open items / invoice paid status

Mars does not maintain authoritative:
- invoice paid/unpaid flag;
- invoice open balance;
- Collection→Sales Invoice allocations;
- Payment→Supplier Invoice allocations;
- open-item settlement state.

V38 Open Items / settlement workspace is superseded as authority.

Due-date/aging information is a rebuildable reporting projection from Account Ledger, not invoice settlement truth.

### FIN-D006 — Customer/Supplier role netting

Same Party may have CUSTOMER and SUPPLIER roles but balances remain distinct.

No automatic netting.

Explicit ROLE_NETTING / MAHSUP is allowed only:
- same company;
- same Party;
- same currency;
- positive eligible customer receivable and positive eligible supplier payable;
- amount <= both eligible balances;
- permission + reason + approval;
- creator != approver.

POST:
- CUSTOMER role CREDIT;
- SUPPLIER role DEBIT;
- Cash/Bank = NONE;
- no Invoice allocation.

### FIN-D007 — Cash Ledger

Cash Ledger is authoritative cash-account balance truth.

Direction:
- IN = positive balance effect.
- OUT = negative balance effect.

Cash Account:
- company + branch scoped;
- exactly one currency;
- ACTIVE / INACTIVE;
- currency cannot change after posted history;
- opening money enters via explicit OPENING transaction, never editable opening/current balance field.

Normal Cash Ledger cannot become negative.
A Cash command that would make cash balance negative is blocked.

Final deactivation requires:
- derived balance = 0;
- no pending transfer/count;
- no unreversed open workflow dependent on the cash account.

### FIN-D008 — Bank Ledger

Bank Ledger is authoritative company bank-book truth.

Direction:
- IN = positive book-balance effect.
- OUT = negative book-balance effect.

Bank Account:
- company + branch scoped;
- fixed currency after posted history;
- ACTIVE / INACTIVE;
- bank/IBAN/reference metadata;
- opening money via explicit OPENING transaction.

Negative Bank book balance:
- blocked unless an explicit Finance Bank Policy defines an overdraft/credit facility for that Bank Account.
- absent policy = block.

Deactivation requires zero book balance and no pending/unreconciled operational blockers.

### FIN-D009 — Treasury transfer

One Finance Transfer transaction owns paired money effects.

Supported:
- Cash → Cash
- Cash → Bank
- Bank → Cash
- Bank → Bank

Same currency:
- source OUT amount = target IN amount;
- same transaction currency;
- no FX difference;
- company net cash/bank money unchanged except explicit fee.

Transfer fee:
- separate explicit fee/expense effect;
- never hidden by making source/target amounts unequal without classification.

Transfer is atomic inside authoritative DB transaction.

### FIN-D010 — FX Transfer

Different-currency treasury movement is FX_TRANSFER.

Required snapshot:
- source account/currency/amount;
- target account/currency/amount;
- executed transaction rate derived from/consistent with source-target amounts;
- base-currency values;
- value date/posting date;
- rate source;
- any bank fee/spread classification;
- actor/audit.

For actual bank-executed conversion, actual executed rate/amounts are transaction authority.

TCMB reference may be retained for comparison/base reference but cannot overwrite actual executed treasury rate.

Manual rate/amount override requires permission, reason and approval.

### FIN-D011 — Commercial-document FX baseline

Frozen Sales/Purchasing policy remains:
- default commercial document reference = TCMB döviz alış;
- document/tax-event date;
- latest prior published business-day fallback;
- manual override permission + reason + approval;
- posted FX snapshot immutable.

Finance does not retroactively rewrite Sales/Supplier Invoice FX snapshot.

### FIN-D012 — Foreign-currency carrying basis and realized FX

Because Mars is balance-only and does not allocate payments to invoices, realized FX cannot depend on Invoice allocation.

Project policy:
- each company + account family/role + entity/account + currency position maintains derived foreign-currency quantity and base-currency carrying value from posted ledger history;
- same-sign open position uses weighted-average carrying base rate;
- a reducing transaction realizes FX against proportional carrying base value;
- when a transaction crosses through zero, split conceptually:
  1. settle the existing position and realize FX;
  2. create the excess opposite-sign advance/position at current transaction rate.

Realized FX recognition occurs when foreign-currency monetary position is reduced/settled/conversion occurs:
- Collection;
- Payment;
- Refund;
- FX Transfer/conversion;
- explicit role netting only when currency/base conversion requires it.

Realized FX is a Finance financial effect and never changes original commercial-document FX snapshot.

### FIN-D013 — Unrealized FX / revaluation

Period-end foreign-currency revaluation is a separate Finance process.

It:
- does not change transaction-currency nominal balances;
- does not mutate original Invoice/Collection/Payment/Bank entries;
- compares carrying base value to revalued base value;
- posts separate unrealized FX adjustment/reversal records.

Exact revaluation rate source/type is owned by a versioned company Finance FX Revaluation Policy.

Fail closed:
- if required policy/rate is unavailable, revaluation cannot POST.

Later revaluation/reversal links preserve prior adjustment history.

This isolates any statutory/accounting-rate detail from commercial-document rate policy.

### FIN-D014 — Advances / prepayments

Customer advance:
- explicit Collection/finance transaction creating customer-role credit position.
- Cash/Bank IN.
- no Invoice allocation.

Supplier advance:
- explicit Payment/prepayment creating supplier-role debit position.
- Cash/Bank OUT.
- no Invoice allocation.

Later Sales/Supplier Invoices naturally change the aggregate role balance.
No automatic invoice settlement relation is created.

Advance refunds use FIN-D015 eligibility rules.

### FIN-D015 — Refund

Customer Refund:
- allowed against eligible customer credit/advance or approved Returns/RMA financial refund entitlement;
- Cash/Bank OUT;
- CUSTOMER role DEBIT, reducing customer credit;
- cannot exceed eligible refund cap.

Supplier Refund received:
- allowed against eligible supplier advance/credit entitlement;
- Cash/Bank IN;
- SUPPLIER role CREDIT, reducing supplier advance position;
- cannot exceed eligible cap.

Refund states:
DRAFT → PENDING_APPROVAL where required → POSTED → REVERSED.

No Refund changes physical stock.

### FIN-D016 — Bank statement import

Imported bank statement rows are external evidence, not Bank Ledger.

Import stores:
- Bank Account;
- provider/file/source;
- statement transaction/reference ID where provided;
- transaction date/value date;
- description;
- currency/amount/direction;
- source checksum/fingerprint;
- raw/evidence reference under secure retention policy;
- import status.

Stable provider transaction identity is the preferred idempotency key.

If provider ID is missing:
- deterministic fingerprint is duplicate-warning evidence;
- suspected duplicate is not silently discarded without explicit resolution.

Import alone never changes book balance.

### FIN-D017 — Bank reconciliation

Reconciliation compares imported statement evidence with authoritative Bank Ledger.

States:
- UNMATCHED
- SUGGESTED
- PARTIALLY_MATCHED
- MATCHED
- EXCEPTION
- IGNORED_WITH_REASON

Auto-match/confidence is suggestion only.

Allowed actions:
- match existing posted Bank movement(s);
- create a proposed Finance transaction from statement evidence;
- split/aggregate reconciliation where totals support it;
- leave unmatched;
- ignore only with permission/reason.

A statement-derived proposed transaction must still pass normal Finance validation/approval and POST before it affects Bank Ledger.

Many-to-many reconciliation is allowed conceptually through explicit matched amounts.
Matched amount cannot exceed either statement-line or Bank-ledger eligible remainder.

### FIN-D018 — Inventory valuation and cost policy

V38 Product/Inventory reference exposes:
Pool / Qty / Carrying Value / Moving Avg / Last Receipt / Late Cost Pending.

Mars freezes perpetual moving weighted average as the default authoritative inventory valuation method for current core planning.

Valuation pool grain:
- company;
- Product or stock-relevant Variant;
- base UOM;
- company base currency.

Warehouse/Location is not a separate valuation pool by default, so internal transfer does not revalue inventory.

Goods Receipt:
- increases physical quantity in Inventory Ledger;
- increases valuation-pool quantity/value using provisional accepted purchase cost basis converted to base currency by Finance valuation FX policy;
- does not create supplier payable.

Moving average after eligible inbound:
new_avg = (prior carrying value + inbound provisional/adjusted base value) / new on-hand quantity.

Sales Dispatch:
- physical quantity OUT;
- valuation pool quantity/value decreases using then-current moving average;
- frozen dispatch cost basis is recorded in Finance cost authority;
- financial COGS is not recognized yet.

Sales Invoice POST:
- recognizes financial COGS from eligible linked frozen Dispatch cost basis;
- does not reduce stock/valuation pool a second time.

Uninvoiced Dispatch cost therefore remains a Finance-owned dispatched-not-invoiced cost bridge until Invoice/reversal/return resolves it.

Supplier Invoice / landed-cost late cost:
- does not add physical stock;
- price/landed-cost delta is source-linked to receipt quantity;
- delta is deterministically apportioned across:
  - remaining on-hand carrying value;
  - dispatched-not-invoiced cost bridge;
  - already recognized COGS where relevant;
- no Product master average-cost overwrite.

Internal Warehouse Transfer:
- no carrying-value change.

Negative Count Adjustment / Scrap:
- removes carrying value at current moving average and records Finance-owned write-off/cost effect.

Positive Count Adjustment:
- if valuation pool has positive existing quantity/value, use current moving average;
- if no valid current moving average exists, explicit unit valuation input + permission + approval is required;
- silent zero-cost positive inventory is forbidden.

Purchase Return:
- physical stock OUT remains Warehouse/Purchasing;
- valuation leaves current pool according to current moving-average valuation;
- supplier financial adjustment remains a separate linked Finance effect.

Landed-cost allocation policy may use value, quantity, weight, volume or an explicitly configured custom driver.
Allocation source and driver are snapshotted/audited.

### FIN-D019 — Credit / risk / hold authority

Finance owns customer-role:
- credit limit;
- financial hold;
- risk evaluation projection.

Party master does not own these balances/signals.

Core exposure projection:
- current positive CUSTOMER receivable exposure from Account Ledger;
- plus Sales-authoritative confirmed/uninvoiced commercial exposure;
- minus eligible customer credit/advance balance.

Conceptual:
exposure = max(0, receivable exposure + unbilled order exposure - eligible credits).

remaining limit = configured credit limit - exposure.

Hold may be:
- manual Finance hold; or
- derived by active Finance Risk Policy when exposure breaches limit/other configured condition.

Sales consumes Finance risk/hold signal; Sales does not write it.

### FIN-D020 — Aging without open-item authority

Customer/Supplier aging is rebuildable reporting only.

Projection input:
- role-specific Account Ledger;
- due date from originating posted entries;
- reversal entries;
- opposite-direction balance movements.

For aging only:
- opposite-direction movements are applied FIFO to oldest eligible due balance segments within Party + role + currency.

This derived matching:
- is not persisted as authoritative Collection/Payment→Invoice allocation;
- does not create Invoice paid/open status;
- cannot drive posting or reversal.

Aging projection can always be rebuilt from ledger.

### FIN-D021 — Posting date / document date / value date

Keep separate:
- document_date: source/legal/business document date;
- posting_date: date controlling Finance posting period and ledger recognition;
- value_date: bank/cash settlement/value-date context where relevant;
- created_at/posted_at: audit timestamps.

Financial recognition uses accepted posting_date according to source workflow.

Bank reconciliation preserves bank transaction date and value date separately.

### FIN-D022 — Posting periods

Posting Period states:
- OPEN
- FROZEN
- CLOSED

OPEN:
- normal authorized posting allowed.

FROZEN:
- normal posting blocked;
- exceptional override requires explicit period-override permission, reason and approval;
- override audit count retained.

CLOSED:
- posting/backdating into period blocked.
- correction of historical closed-period transaction uses linked reversal/correction in an eligible open period.
- reopening CLOSED period is a separate high-risk Settings/Finance action with explicit permission, reason, approval and audit; ordinary transaction permission cannot reopen it.

No silent date change to bypass period state.

### FIN-D023 — Reversal

Posted Account/Cash/Bank/Valuation movement is immutable.

Reversal:
- new compensating entry/entries;
- original link retained;
- transaction/base currency values copied/reversed according to original snapshot;
- downstream reconciliation/FX/cost dependencies revalidated;
- reason and permission required.

Reversal never edits/deletes original ledger row.

### FIN-D024 — Idempotency / duplicate posting

Durable idempotency required for:
- Collection POST;
- Payment POST;
- Refund POST;
- Transfer/FX Transfer POST;
- statement import;
- statement-derived transaction creation;
- reconciliation confirmation;
- valuation late-cost processing;
- revaluation;
- reversal.

Valkey/cache is not sufficient to guarantee financial idempotency.

### FIN-D025 — Cash count

V38 Cash Count is retained.

Cash Count:
DRAFT → COUNTING → REVIEW → PENDING_APPROVAL when discrepancy exists → POSTED.

Count uses Cash Ledger snapshot/reconciliation principle.
Non-zero discrepancy:
- requires approval;
- creates explicit CASH_ADJUSTMENT ledger entry;
- never overwrites cash balance.

Counter cannot approve own non-zero adjustment.

## 5. Effect matrix

| Action | CUSTOMER | SUPPLIER | CASH | BANK | STOCK | COST |
|---|---|---|---|---|---|---|
| Sales Invoice POST | Debit/increase AR | NONE | NONE | NONE | NONE | COGS recognize if eligible |
| Collection POST | Credit/decrease AR | NONE | IN if cash | IN if bank | NONE | realized FX if applicable |
| Supplier Invoice POST | NONE | Credit/increase AP | NONE | NONE | NONE | late-cost/variance as applicable |
| Payment POST | NONE | Debit/decrease AP | OUT if cash | OUT if bank | NONE | realized FX if applicable |
| Customer Refund | Debit/reduce customer credit | NONE | OUT | OUT | NONE | NONE |
| Supplier Refund | NONE | Credit/reduce supplier advance | IN | IN | NONE | NONE |
| Role Netting | Credit | Debit | NONE | NONE | NONE | FX if cross-base treatment applies |
| Treasury Transfer | NONE | NONE | paired IN/OUT | paired IN/OUT | NONE | realized FX/fees if applicable |
| Bank Statement Import | NONE | NONE | NONE | NONE | NONE | NONE |
| Reconciliation | NONE | NONE | NONE | NONE | NONE | NONE |
| Stock Count/Scrap valuation | NONE | NONE | NONE | NONE | physical owned elsewhere | valuation/write-off only |

## 6. Reviewer outcome

Accounting:
- receivable/payable direction, cash/bank effects, FX, valuation and reversal are deterministic.

ERP:
- commercial source documents and Finance effects do not double-post physical stock.

Database:
- ledgers remain authoritative; role separation prevents accidental Customer/Supplier netting.

Architecture:
- Finance owns monetary/cost truth while Sales/Purchasing/Warehouse own source workflow and physical stock.

Security:
- posting, reversal, netting, override, revaluation and adjustment require server-side scoped permission/SoD.

Testing:
- duplicate post, FX, reconciliation, advances, period locks and valuation races are testable.

UX:
- balance effect, money effect, book-vs-statement and reversal state can be shown without false Invoice settlement state.
