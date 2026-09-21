# Checks / Promissory Notes Module Plan

Status: PLAN-008 COMPLETED / FROZEN — planning only.

## Purpose
Own the identity, custody and lifecycle of incoming/outgoing CHECK and PROMISSORY_NOTE instruments while Finance remains the sole authority for Party Account, Cash and Bank balances.

## Frozen model
- Instrument lifecycle/custody is distinct from financial recognition.
- Incoming acceptance posts CUSTOMER_RECEIVABLE CREDIT and creates an explicit instrument receivable position; Cash/Bank remains NONE until actual settlement.
- Outgoing delivery posts SUPPLIER_PAYABLE DEBIT and creates an explicit instrument payable obligation; Cash/Bank remains NONE until actual clearing/payment.
- Instrument positions are Finance-owned monetary subledger positions, not Party/Cash/Bank balance authorities and not Invoice allocations.
- Incoming bank handoff and endorsement do not create Cash/Bank money.
- Actual incoming settlement creates Bank/Cash IN and extinguishes the eligible instrument receivable.
- Actual outgoing clearing creates Bank OUT and extinguishes the eligible instrument payable.
- Bounce/unpaid/return uses explicit compensating movements; original history is retained.
- Nominal amount/currency and accepted identity snapshots are immutable.
- Partial movements are allowed for collection/settlement where the external/legal event supplies an accepted amount; partial endorsement is forbidden in core PLAN-008.
- No physical STOCK effect exists.

## Outputs
- plan.md
- workflows.md
- forms.md
- data-contract.md
- permissions.md
- integrations.md
- reports.md
- acceptance-criteria.md
- full-test-day.md

No SQL, application code, migration, deployment or heavy tests were produced.