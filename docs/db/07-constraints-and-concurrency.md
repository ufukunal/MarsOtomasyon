# Logical Constraints and Concurrency Guarantees

Status: FROZEN — PLAN-010. This file defines required outcomes, not DDL or lock syntax.

## 1. Identity and scope
Future physical schema must guarantee:
- active/effective ERP permission grant uniqueness for Actor + Company + PermissionCode;
- Party Code unique in accepted company scope;
- deterministic active tax identity collision prevention;
- Product/SKU/code and Barcode mapping uniqueness in accepted company/namespace scope;
- Warehouse/Location code uniqueness in accepted scope;
- Lot identity uniqueness in Product/Variant/company scope;
- Serial identity uniqueness and no simultaneous authoritative physical position;
- provider external mapping uniqueness in provider/account/system scope;
- business/document numbering uniqueness in its configured company/branch/type scope;
- same-company compatibility of transactional/source-target references.

## 2. Positive and valid values
Require positive amounts/quantities where a movement/link exists.
Direction/state fields carry semantic sign; negative magic amounts are not used to bypass direction rules.
Decimal precision is preserved.

## 3. Source cumulative caps
Durably protect at transaction time:
- Quote converted <= effective offered;
- Dispatch <= eligible Order remainder;
- Sales Invoice <= eligible source basis;
- Goods Receipt <= PO + approved tolerance;
- Supplier Invoice <= eligible Receipt/PO + approved exception;
- Return physical <= eligible source less prior net return;
- reconciliation match <= both unmatched remainders;
- instrument processed amount <= eligible remaining.

Cross-row caps require locking/version/serializable design or equivalent durable PostgreSQL strategy; a check constraint alone is insufficient.

## 4. Reservation / stock
Reservation and physical posting must coordinate against current eligible AVAILABLE quantity.
Normal commands cannot produce negative physical stock.
Dispatch posting revalidates physical authority; Pick is not enough.

## 5. Serial
Concurrent movement of same serial must allow only one accepted next position.
Duplicate offline/online operation cannot create duplicate serial presence.

## 6. Idempotency
Durable uniqueness/result semantics required for:
- every externally retryable POST/reversal;
- offline scan operation;
- provider callback/import;
- Collection/Payment/Refund;
- transfer;
- statement import/derived transaction;
- reconciliation confirmation;
- valuation allocation/revaluation;
- instrument settlement/payment;
- Return receipt/shipment/credit/refund.

Valkey lock may reduce contention but cannot be sole guarantee.

## 7. Reversal
One logical reversal request cannot generate multiple compensating effects.
Original/reversal relation remains explicit.
Dependent downstream effects may require compensation chain rather than direct reversal.

## 8. Finance
Cash normal spend cannot exceed eligible balance unless accepted overdraft policy applies to Bank.
Finance posting validates Posting Period.
Role netting validates same Party/company/currency and eligible role positions.
No transaction creates an Invoice allocation row.

## 9. Bank reconciliation
Matched amount > 0.
Cumulative match cannot exceed Statement Line eligible amount or Bank Ledger unreconciled amount.
Account/currency compatibility required.

## 10. Checks / Notes
Accepted instrument identity collision is a deterministic conflict/review, never silent merge.
Concurrent settlement/endorsement/bank handoff uses current remaining + custody state.
Partial endorsement remains forbidden.

## 11. Returns
Source-less/over-source scope requires exact approval evidence.
Same serial cannot be returned twice.
Customer return receipt racing QC/reversal and supplier return racing stock movement must return conflict rather than silently overwrite.

## 12. Optimistic versions
Mutable workflow aggregates such as Party master, Product master, Order current version, Return Case, Transfer, Count and Instrument current controlled state require stale-write detection.
Exact token/column strategy remains physical design.

## 13. Reviewer testability
Every invariant above maps to a future duplicate, stale-state, boundary or concurrency test.
Heavy concurrency/PostgreSQL proof is deferred to Full Test Day.


## 14. ERP permission grants

ADR-0005 requires:
- current PostgreSQL grant state is authoritative;
- authentication/token claims alone cannot authorize an ERP command;
- permission checks combine Actor + trusted Company + PermissionCode;
- duplicate active effective grants for the same scope are durably prevented;
- revoke/disable state takes effect on subsequent authoritative evaluations;
- branch scope is not added until a concrete module requires it.

Heavy authorization concurrency/security regression remains Full Test Day scope.
