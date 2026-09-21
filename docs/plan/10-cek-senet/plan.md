# Checks / Promissory Notes Domain Plan

Status: COMPLETED / FROZEN — PLAN-008

## 1. Objective
Freeze deterministic identity, custody, financial recognition, partial processing, reversal and source-target semantics for checks and promissory notes without creating a second Account/Cash/Bank or Invoice-settlement authority.

## 2. Authority boundary
Checks/Notes owns instrument identity, lifecycle, custody, movement history and source-target lineage.
Finance owns Account Ledger, Cash Ledger, Bank Ledger, FX carrying/realized effects and the instrument receivable/payable monetary positions created by these workflows.
Reports/projections are rebuildable and never authority.

## 3. Identity
Each accepted instrument freezes:
- type: CHECK or PROMISSORY_NOTE;
- direction: INCOMING or OUTGOING;
- instrument number/reference and issuer/drawer;
- beneficiary/payee and linked Party role;
- bank/branch/account reference where applicable;
- currency and immutable original nominal amount;
- issue and maturity/due dates;
- company and applicable branch;
- physical/digital custody reference;
- Party/legal identity snapshot and instrument metadata snapshot.

Duplicate detection uses deterministic company + type + direction + issuer identity + normalized instrument reference + currency + nominal amount, with bank identity included for checks where available. Ambiguous collision blocks acceptance for review; fuzzy similarity is warning-only.

## 4. Recognition decisions
### Incoming customer instrument
On accepted receipt/POST:
- CUSTOMER_RECEIVABLE CREDIT for accepted amount;
- Finance instrument receivable position INCREASE for same currency/amount;
- Cash/Bank NONE.
This is balance-only settlement of the Customer role, not Invoice allocation.

On actual settlement:
- instrument receivable DECREASE;
- Cash/Bank IN;
- no second CUSTOMER credit.

On bounce/return after prior customer credit:
- instrument receivable DECREASE/close for affected amount;
- CUSTOMER_RECEIVABLE DEBIT restores the claim;
- Cash/Bank reversal only if money had actually been posted and is subsequently reversed.

### Outgoing supplier instrument
On accepted delivery/POST:
- SUPPLIER_PAYABLE DEBIT for accepted amount;
- Finance instrument payable obligation INCREASE;
- Cash/Bank NONE.
This is balance-only settlement of the Supplier role.

On actual clearing/payment:
- instrument payable DECREASE;
- Cash/Bank OUT;
- no second SUPPLIER debit.

If cancelled/returned before clearing:
- instrument payable DECREASE/close;
- SUPPLIER_PAYABLE CREDIT restores the obligation;
- Cash/Bank NONE.

## 5. Endorsement / ciro
Only eligible incoming portfolio instruments may be endorsed.
Core PLAN-008 permits whole-remaining-eligible-amount endorsement only; partial endorsement is forbidden because a single negotiable instrument cannot be silently split into multiple custody owners.
Target must be an eligible Supplier Party in same company and currency.
POST:
- incoming instrument receivable position is transferred/closed for endorsed eligible amount;
- SUPPLIER_PAYABLE DEBIT reduces supplier payable;
- Cash/Bank NONE;
- no CUSTOMER movement at endorsement because customer settlement already occurred at receipt.
If endorsement is returned/unpaid, explicit compensation restores the supplier payable and re-establishes the instrument position/custody according to returned evidence; no silent rollback.

## 6. Bank collection
DELIVER_TO_BANK changes custody only.
Bank Ledger remains unchanged until confirmed settlement evidence is accepted and Finance POST succeeds.
Fees/commission are separate explicit Finance effects and never hidden in principal.
Partial bank collection is allowed only when accepted settlement evidence states the settled amount; processed + remaining remain visible.

## 7. Partial processing
Original nominal amount is immutable.
Movement records carry processed amount.
Cumulative active processed amount cannot exceed eligible remaining nominal amount.
Partial collection/settlement is allowed with evidence.
Partial endorsement is forbidden.
A partially settled instrument remains open with exact processed and remaining amounts; terminal COLLECTED/PAID requires remaining = 0.

## 8. Bounce / protest / return
BOUNCED/UNPAID is a financial exception; PROTESTED is additional legal/evidence state where applicable and does not itself duplicate financial restoration.
Return/cancellation after a recognition effect uses linked compensation.
Fees/expenses are separate Finance movements.
Original instrument and every custody/financial movement remain visible.

## 9. FX
Nominal instrument currency/amount never changes.
Receipt/delivery recognition freezes Finance base-value/rate snapshot under PLAN-007 carrying rules.
Actual settlement uses settlement-date actual/reference evidence; realized FX belongs Finance.
Original commercial/instrument FX snapshots are never rewritten.

## 10. Period/reversal
Financial POST obeys Finance OPEN/FROZEN/CLOSED period controls.
Posted effects are append/reversal only.
Reversal validates dependent later movements and uses compensating entries; no hard delete or silent state rollback.

## 11. Scope
Company is mandatory. Cross-company instrument movement/endorsement is forbidden.
Branch is explicit where custody/account ownership requires it.
Checks/Notes never creates physical stock movement.

## 12. V38 classification
KEEP/ADAPT: incoming/outgoing checks/notes lists, instrument detail, maturity, custody/status, movement history, Party/Finance linkage, files/timeline, endorse, bank handoff, collect, return, protest concepts.
ADAPT: all actions to the frozen recognition rules above; UI state cannot imply money realization before Finance settlement.
