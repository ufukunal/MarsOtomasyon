# Checks / Promissory Notes Forms Contract

Status: FROZEN planning contract.

## 1. Portfolio lists
Separate user views may expose Incoming Checks, Outgoing Checks, Incoming Notes and Outgoing Notes, but all use one coherent instrument contract.

Columns/filters:
- type/direction;
- instrument number;
- Party;
- issuer/payee;
- bank/branch where applicable;
- issue/due date;
- currency;
- original nominal;
- processed;
- remaining;
- lifecycle;
- custody;
- financial state;
- overdue/exception flags.

## 2. Instrument detail
Must show distinctly:
- immutable identity/snapshot;
- linked Party role;
- original / processed / remaining;
- current custody;
- current financial state;
- maturity;
- source/target relationships;
- movement timeline;
- Account/Finance posting references;
- files/evidence;
- reversal/original lineage.

## 3. Registration
Required before POST:
type, direction, Party/role, instrument reference, issuer/payee, currency, nominal amount > 0, dates, company; bank metadata for check where available.
Duplicate collision is visible and blocks accepted POST until resolved.

## 4. Actions
Contextual actions only:
- Receive/Post;
- Endorse;
- Deliver to Bank;
- Record Settlement/Collection;
- Record Payment/Clearing;
- Bounce/Unpaid;
- Protest;
- Return;
- Reverse.

Every risky action previews:
- amount/currency;
- Party Account effect;
- instrument-position effect;
- Cash/Bank effect;
- resulting custody/state;
- approval/reason requirement.

## 5. Partial UX
Collection/payment dialog shows original, previously processed, eligible remaining and current processed amount.
Amount cannot exceed remaining.
Endorse action does not offer partial amount in core PLAN-008.

## 6. High-volume maturity work
Support filtering/bulk selection by due date, bank custody, Party, type and state.
Bulk actions must validate each instrument independently and return per-item success/failure; no partial failure is hidden.

## 7. Error/status UX
Hard errors: duplicate identity, stale state, over-processing, wrong company/currency, unauthorized custody, duplicate settlement, invalid dependent reversal.
Status is never color-only.
Posted action uses reversal terminology, not delete/undo.
