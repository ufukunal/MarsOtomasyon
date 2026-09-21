# Checks / Promissory Notes Conceptual Data Contract

Status: FROZEN logical/domain planning only. No SQL/schema/index/type is defined.

## 1. Ownership
Checks/Notes authoritative:
- Instrument identity/snapshot;
- lifecycle and custody;
- instrument movement history;
- source-target lineage.

Finance authoritative:
- CUSTOMER/SUPPLIER Account Ledger;
- Cash/Bank Ledger;
- instrument receivable/payable monetary positions and FX effects.

No mutable Party/Cash/Bank balance is owned here.

## 2. Conceptual records
### Instrument
Immutable accepted core:
company, applicable branch, type, direction, normalized reference, issuer/drawer, beneficiary/payee, Party + financial role, bank metadata, currency, original nominal amount, issue date, maturity, accepted snapshot.

### Instrument Movement
Append-oriented:
instrument, action/type, processed amount, from/to lifecycle, from/to custody, financial effect reference, source/target Party or Bank context, actor/time, reason, approval, idempotency identity, reversal relation.

### Instrument Financial Position Reference
Links instrument movements to Finance-owned receivable/payable position movements. It is not a copied balance.

### Source/Target Link
Normalized relation for:
- receipt source evidence → instrument;
- instrument → bank handoff;
- instrument → endorsement target Party;
- instrument → settlement/payment evidence;
- original movement → reversal/compensation.
No comma-separated IDs.

## 3. Amount invariants
- original nominal amount > 0 and immutable after accepted POST;
- processed movement amount > 0;
- cumulative active processed amount <= eligible amount;
- remaining = nominal - net eligible processed;
- terminal monetary settlement requires remaining = 0;
- partial collection/payment may create multiple movement records;
- partial endorsement is forbidden in core.

## 4. Identity/duplicates
Future P3 must provide a durable uniqueness/concurrency strategy for accepted instrument identity and external settlement identity.
Potential deterministic identity uses company/type/direction/issuer/reference/currency/nominal plus check bank identity when available.
Collision resolution must not silently merge distinct instruments.

## 5. Snapshots
Accepted historical snapshots preserve Party legal/display identity, issuer/payee, instrument reference, bank metadata, currency/nominal, dates and relevant custody evidence.
Live Party/Bank master edits never rewrite accepted history.

## 6. State/history
Current lifecycle/custody may be projected from append movements/current controlled state, but posted movement history is immutable.
Financial state derives from Finance position/ledger references.
Custody and financial state are separate dimensions.

## 7. Scope
Company mandatory; cross-company source/target/endorsement forbidden.
Branch placement belongs P3 ownership analysis but custody/account branch must be enforceable.

## 8. Concurrency/idempotency risks for P3
Protect:
- duplicate registration;
- same remaining amount processed concurrently;
- duplicate bank settlement/payment;
- stale custody transition;
- endorsement racing bank handoff;
- bounce racing settlement;
- duplicate reversal;
- cross-company links.
Valkey may coordinate but cannot be sole correctness guarantee.
