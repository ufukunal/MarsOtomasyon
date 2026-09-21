# PLAN-007 Acceptance Criteria

Status: COMPLETED / FROZEN — planning only.

## 1. Financial authority

- [x] Account Ledger is authoritative Party balance truth.
- [x] Cash Ledger is authoritative Cash balance truth.
- [x] Bank Ledger is authoritative Bank book balance truth.
- [x] Inventory Valuation/Cost authority is Finance-owned.
- [x] mutable Party/Cash/Bank/Product balance/cost fields are not authoritative.
- [x] posted financial history is append/reversal, not silent mutation.

## 2. Party role / settlement

- [x] CUSTOMER receivable debit/credit direction is deterministic.
- [x] SUPPLIER payable debit/credit direction is deterministic.
- [x] Customer and Supplier role balances remain separate.
- [x] automatic cross-role netting is forbidden.
- [x] explicit same-Party role netting requires permission/reason/approval/SoD.
- [x] Collection is balance-only and has no Invoice allocation authority.
- [x] Payment is balance-only and has no Supplier Invoice allocation authority.
- [x] no authoritative Invoice paid/open/open-item model is introduced.
- [x] aging is rebuildable reporting only.

## 3. Collection / Payment / advance / refund

- [x] Collection = CUSTOMER CREDIT + Cash/Bank IN.
- [x] Payment = SUPPLIER DEBIT + Cash/Bank OUT.
- [x] customer excess Collection becomes explicit Customer Advance only.
- [x] supplier overpayment becomes explicit Supplier Advance only.
- [x] Customer Refund direction/cap is deterministic.
- [x] Supplier Refund direction/cap is deterministic.
- [x] none of these transactions changes physical stock.

## 4. Cash / Bank / treasury

- [x] Cash IN/OUT and Bank IN/OUT semantics are deterministic.
- [x] Cash normal negative balance is blocked.
- [x] Bank negative book balance requires explicit overdraft policy.
- [x] same-currency transfer uses paired source OUT + target IN atomically.
- [x] fees are explicit, not hidden in transfer imbalance.
- [x] FX Transfer stores source/target amounts, executed rate/base values and fees.
- [x] Cash/Bank opening values are explicit transactions rather than editable balance fields.
- [x] Cash Count posts only explicit approved CASH_ADJUSTMENT.

## 5. FX / periods / reversal

- [x] frozen Sales/Purchasing TCMB commercial-document FX contract remains unchanged.
- [x] realized FX works without Invoice allocation using weighted carrying basis.
- [x] zero-crossing foreign-currency position behavior is deterministic.
- [x] unrealized FX is separate revaluation workflow.
- [x] revaluation rate/type is versioned Finance Policy and fail-closed when unavailable.
- [x] document/posting/value dates are distinct.
- [x] OPEN/FROZEN/CLOSED period behavior is defined.
- [x] closed-period correction does not silently back-edit historical posting.
- [x] reversal creates linked compensating records.

## 6. Statement / reconciliation

- [x] statement import is evidence/staging and does not change Bank Ledger.
- [x] stable external transaction ID/fingerprint duplicate handling is defined.
- [x] suggestion/confidence is non-authoritative.
- [x] existing Bank movement matching is explicit.
- [x] create-from-statement still requires normal Finance POST.
- [x] partial/many-to-many matched-amount limits are deterministic.
- [x] unmatched rows remain unmatched.
- [x] ignored rows require permission/reason.
- [x] reversed reconciled movement returns reconciliation to review/exception.

## 7. Inventory valuation / cost

- [x] perpetual moving weighted average is the frozen current valuation method.
- [x] valuation pool grain is company + Product/Variant + Base UOM + base currency.
- [x] Warehouse transfer does not revalue inventory.
- [x] Goods Receipt creates provisional inventory value without supplier payable.
- [x] Sales Dispatch removes carrying value and creates dispatched-not-invoiced cost bridge.
- [x] Sales Invoice recognizes COGS from eligible bridge and does not reduce stock/value again.
- [x] Supplier Invoice/landed-cost late delta is source-linked and split across on-hand/bridge/COGS.
- [x] negative count/scrap removes moving-average carrying value.
- [x] positive count with no valid average requires explicit approved unit valuation.
- [x] silent zero-cost positive inventory is forbidden.
- [x] Purchase Return physical and supplier financial effects stay separate.

## 8. Risk / permissions / scope

- [x] Finance owns credit limit/risk/hold.
- [x] exposure consumes Finance receivable + Sales unbilled exposure - eligible credits.
- [x] Sales consumes Finance signal but does not write it.
- [x] company/branch scope is explicit.
- [x] Payment/netting/refund/reversal/revaluation/manual valuation/period override SoD and permissions are explicit.
- [x] statement/bank sensitive data handling is bounded.

## 9. Integration / reports / idempotency

- [x] durable idempotency requirements cover all financial POST/reversal/import/reconciliation/cost effects.
- [x] Valkey/cache is never sole financial correctness guarantee.
- [x] outbox/provider state does not replace local financial authority.
- [x] reports/read models derive from authoritative ledgers.
- [x] internal treasury transfers are excluded from external cash-flow double counting.
- [x] gross margin uses recognized Sales revenue + Finance COGS, not mutable Product cost.
- [x] Full Test Day backlog covers duplicate/concurrency/FX/reconciliation/period/valuation/security risks.

## 10. V38 migration

- [x] Collection/Payment/Refund/Cash/Bank/Transfer/Statement/Reconciliation/Risk/Inventory Cost product concepts are mapped.
- [x] V38 Open Items / Settlement Workspace invoice-allocation semantics are explicitly superseded as authority by frozen Sales B001.
- [x] Checks/Promissory Notes are not started inside PLAN-007.

## 11. Fast verification

Before handoff:
- all ten Finance/Treasury planning files exist and are non-empty;
- no placeholder remains;
- Account/Cash/Bank ledgers are authoritative;
- Collection/Payment remain balance-only;
- no Invoice allocation/open-item authority appears;
- Customer/Supplier role auto-netting is absent;
- statement import does not post Bank Ledger;
- FX/reversal/period semantics are deterministic;
- moving-average valuation and Dispatch cost bridge are coherent;
- Sales Invoice COGS cannot double-reduce inventory carrying value;
- positive count cannot silently enter zero-cost stock;
- no SQL/migration/C#/TypeScript implementation was added;
- final real main HEAD is reverified.

## 12. Exit condition

PLAN-007 Finance / Treasury planning is frozen.

Dependency-safe next P2 work package:
Checks / Promissory Notes workflow contract.

The exact repository task ID/path must be verified from repository structure/task records before state is advanced. Returns/RMA remains after Checks/Notes.

Logical database planning cannot begin until the remaining P2 dependencies are sufficiently frozen.
