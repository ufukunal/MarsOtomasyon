# Finance / Treasury Integration Contract

Status: FROZEN planning contract. No provider/bank implementation.

## 1. General transaction rule

Business command
→ validate authority/policy/period/idempotency
→ PostgreSQL transaction with Finance ledger effects
→ outbox when asynchronous/external side effect is needed.

External provider response is not financial authority.

## 2. Candidate events

- CollectionPosted
- CollectionReversed
- PaymentPosted
- PaymentReversed
- RefundPosted
- RefundReversed
- RoleNettingPosted
- TreasuryTransferPosted
- FxTransferPosted
- BankStatementImported
- BankReconciliationConfirmed
- FxRevaluationPosted
- InventoryLateCostAdjusted
- InventoryCostBridgeRecognized
- CashAdjustmentPosted
- FinancialHoldChanged

Events carry stable Finance transaction/source identities, not mutable balance snapshots as authority.

## 3. Sales integration

Sales Invoice POST supplies source snapshot and triggers/participates in:
- CUSTOMER Account Ledger DEBIT;
- COGS recognition from eligible cost basis.

Collection remains Finance transaction:
- customer Account CREDIT;
- Cash/Bank IN;
- no Invoice allocation.

Sales risk evaluation consumes Finance risk/hold signal.

Finance never changes Sales physical Dispatch or Invoice snapshot.

## 4. Purchasing integration

Supplier Invoice POST supplies:
- SUPPLIER Account Ledger CREDIT;
- late-cost/price variance source.

Payment is Finance-owned:
- SUPPLIER DEBIT;
- Cash/Bank OUT;
- no Supplier Invoice allocation.

Purchasing Goods Receipt remains stock quantity source; Finance consumes receipt lineage for valuation only.

## 5. Warehouse / Inventory integration

Inventory Ledger is physical quantity truth.

Finance valuation consumes accepted physical events:
- Goods Receipt;
- Dispatch;
- Count Adjustment;
- Scrap;
- Purchase Return;
- reversal.

Critical physical + valuation consistency should be committed atomically inside the same PostgreSQL transaction boundary where the business event requires both effects.

Do not depend on asynchronous outbox to eventually invent missing authoritative cost for a successfully posted physical mutation.

Outbox may publish the committed result afterward.

## 6. Dispatch → COGS bridge

Dispatch POST:
- Inventory quantity OUT;
- valuation pool value OUT;
- dispatched-not-invoiced cost bridge IN.

Sales Invoice POST:
- bridge reduction;
- COGS recognition.

Retry/idempotency protects both.

If downstream state makes reversal non-trivial, compensating workflow is required; no silent bridge reassignment.

## 7. Supplier Invoice late cost

Supplier Invoice/landed-cost event references receipt lineage.

Finance determines cost delta split across:
- on-hand valuation;
- dispatched-not-invoiced bridge;
- recognized COGS.

Late-cost processing is idempotent by source invoice/cost component identity.

No Product mutable average-cost update.

## 8. Bank statement provider/import

Provider/file adapters produce Statement Import evidence.

Required provider concerns at implementation:
- authentication/secret storage;
- source account mapping;
- stable transaction ID if available;
- pagination/cutoff;
- timezone/date/value-date;
- retry;
- duplicate delivery;
- raw evidence retention;
- reconciliation.

Current PLAN-007 does not assume any specific bank/API capability.

Implementation-time provider capability must be verified before adapter behavior is frozen.

## 9. File statement import

V38 MT940 example is product reference, not exclusive format.

Adapter/parser boundary:
- raw file;
- normalized statement rows;
- checksum;
- parsing errors;
- duplicate evidence.

Import never directly posts Bank Ledger.

## 10. Reconciliation integration

Auto-match engine may suggest based on:
- stable reference;
- amount;
- currency;
- date/value-date;
- Party/text/reference clues.

Suggestion confidence has no financial effect.

Operator-confirmed reconciliation links only posted Bank Ledger.

"Create from statement" creates a draft/proposed Finance transaction and must pass normal authorization/approval/POST.

## 11. FX rates

Commercial documents preserve frozen TCMB-based Mars default.

Finance rate adapter may provide:
- TCMB reference;
- revaluation-policy reference;
- actual bank-executed FX evidence.

Rate fetch/cache:
- can be cached for performance;
- PostgreSQL-posted snapshot remains authoritative for posted transaction.

Provider/rate outage:
- no silent random fallback;
- use accepted prior-business-day rule where already frozen;
- otherwise block or require controlled manual override according to workflow.

## 12. Bank payment/provider send

If later an outbound payment provider/API is introduced:
- local approved Payment intent and provider execution state remain distinct;
- ambiguous timeout is not blindly retried if duplicate transfer risk exists;
- provider query/reconciliation required;
- Bank Ledger POST point must be explicitly mapped to confirmed execution semantics before implementation.

PLAN-007 does not invent this provider-specific point.

## 13. Cash

Cash transactions have no external provider dependency by default.

Receipt/print integration:
- asynchronous/print concern;
- cannot change ledger truth.

## 14. Risk signal

Finance may emit:
- CustomerFinancialHoldChanged
- CustomerRiskExposureChanged

Sales consumes projection/signal and must revalidate authoritative Finance state for high-risk command when implementation requires.

Realtime/cache is advisory.

## 15. Posting periods

Cross-module posting commands consume authoritative period gate.

Modules cannot cache OPEN state and bypass a later Freeze/Close.

Period change may emit invalidation/realtime event, but server DB authority is checked on POST.

## 16. Idempotency/retry

Financial effects require durable PostgreSQL-level guarantee.

Candidates:
- invoice financial posting;
- Collection;
- Payment;
- Refund;
- role netting;
- transfer;
- FX transfer;
- revaluation;
- statement import;
- statement-derived transaction;
- reconciliation confirmation;
- late cost;
- COGS bridge consumption;
- reversal.

Valkey locks may coordinate but are never the sole correctness guarantee.

## 17. Observability

Trace:
- company/branch;
- Finance transaction;
- source document;
- money account;
- Party role;
- currency/base amount;
- provider/import batch;
- operation/idempotency ID;
- reconciliation;
- policy/rate version;
- outbox/provider attempts;
- correlation.

Sensitive bank data and credentials are masked/redacted.
