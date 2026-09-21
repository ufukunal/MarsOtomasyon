# Checks / Promissory Notes Reporting Contract

Status: FROZEN PLAN-008 reporting semantics. Reports are not authority.

## 1. Portfolio
Grain: instrument.
Show type/direction, Party, reference, due date, nominal, processed, remaining, lifecycle, custody, financial state, currency and overdue/exception flags.

## 2. Maturity schedule
Bucket by due date with explicit as-of date and currency.
Separate incoming expected receipts from outgoing obligations.
Do not treat expected maturity as Cash/Bank money.

## 3. Incoming / outgoing
Filter by CHECK/PROMISSORY_NOTE, Party, company/branch, status and date.
Incoming exposure and outgoing obligation use Finance instrument positions plus instrument identity.

## 4. Custody
Portfolio, endorsed Party, bank custody, returned and settled archive are separate views.
Custody report does not imply financial realization.

## 5. Settlement
Collected/paid report reconciles instrument movements to Finance Cash/Bank entries.
Partial instruments show original, processed and remaining.
Duplicate/exception flags are visible.

## 6. Bounce / protest / overdue
Show original instrument, Party, due date, unpaid amount, restoration/compensation reference, protest evidence/fee and current custody.
Protest without separate financial effect must not inflate totals.

## 7. Party exposure
Party view may show:
- Customer/Supplier Account role balance from Account Ledger;
- incoming instrument receivable exposure;
- outgoing instrument payable obligation;
- endorsed instruments.
These are labelled components; they are not auto-netted and report is not authority.

## 8. FX
Show nominal foreign amount, recognition base value/rate, settlement base value/rate and Finance realized FX reference.
Original snapshot remains visible.

## 9. Security/export
Apply company/branch/Finance permissions and bank-data masking.
Exports use the same filters/security.
