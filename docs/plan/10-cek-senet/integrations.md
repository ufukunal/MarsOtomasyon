# Checks / Promissory Notes Integration Contract

Status: FROZEN planning contract. No provider implementation.

## 1. Transaction boundary
Instrument command
→ validate identity/state/custody/permission/period/idempotency
→ one PostgreSQL transaction commits instrument movement plus required Finance authoritative effects
→ outbox publishes committed result where needed.

A provider/bank response is evidence, not business truth.

## 2. Finance
Incoming receipt atomically links CUSTOMER CREDIT with instrument receivable increase.
Outgoing delivery atomically links SUPPLIER DEBIT with instrument payable increase.
Settlement atomically links position decrease with Cash/Bank movement.
Compensation/reversal keeps original lineage.

## 3. Party
Party supplies company-scoped identity/role.
Accepted instrument freezes required identity snapshot.
No cross-role auto-netting is introduced.

## 4. Bank handoff
Bank custody/handoff is separate from settlement.
Future bank/provider adapter may submit evidence/status, but cannot mutate Bank Ledger directly.
Confirmed settlement must pass normal Finance authorization/period/idempotency.

## 5. Settlement evidence
Stable external transaction/reference is retained when available.
Duplicate callback/import is idempotent.
Ambiguous timeout/retry never blindly posts a second settlement; query/reconciliation is required.

## 6. Reconciliation
Bank statement remains Finance evidence.
It may match an instrument-originated posted Bank movement but cannot itself convert bank custody into collected state without accepted settlement workflow.
Reconciliation never mutates ledger truth.

## 7. Candidate events
- InstrumentReceivedPosted
- InstrumentIssuedDelivered
- InstrumentEndorsed
- InstrumentDeliveredToBank
- InstrumentPartiallySettled
- InstrumentSettled
- InstrumentBounced
- InstrumentProtested
- InstrumentReturned
- InstrumentMovementReversed

Events carry stable identities/references, not mutable balances.

## 8. FX
Finance owns carrying/realized FX.
Posted instrument snapshot and recognition rate are retained; later rate changes do not rewrite history.

## 9. Files
Scans/images/protest/return/bank evidence may be linked later through Files abstraction.
Files are evidence; executable/upload security and retention belong shared file policy.

## 10. Observability
Trace instrument, company/branch, Party role, movement, amount/currency, custody, Finance transaction, bank evidence, operation/idempotency and correlation IDs. Mask sensitive bank data.
