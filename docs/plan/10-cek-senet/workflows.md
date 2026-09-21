# Checks / Promissory Notes Workflows

Status: FROZEN — PLAN-008

## 1. State dimensions
Do not collapse these dimensions:
- lifecycle state;
- custody state;
- financial position state.

Custody: PARTY_RECEIVED/PORTFOLIO, ENDORSED_PARTY, BANK_CUSTODY, RETURNED, SETTLED_ARCHIVE.
Financial: UNRECOGNIZED, INSTRUMENT_RECEIVABLE_OPEN/PARTIAL/CLOSED, INSTRUMENT_PAYABLE_OPEN/PARTIAL/CLOSED, RESTORED_TO_PARTY_BALANCE.

## 2. Incoming CHECK
REGISTERED_DRAFT → RECEIVED_POSTED → PORTFOLIO.
From PORTFOLIO:
- DELIVERED_TO_BANK → PARTIALLY_COLLECTED → COLLECTED;
- ENDORSED → SETTLED_BY_ENDORSEMENT;
- RETURNED;
- BOUNCED/UNPAID → optional PROTESTED → RETURNED/RESTORED.
Draft may CANCEL. Posted correction uses REVERSED/compensation.

Receipt POST: CUSTOMER CREDIT + instrument receivable increase; no Cash/Bank.
Bank handoff: custody only.
Collection: instrument receivable decrease + Bank/Cash IN.
Bounce/return after receipt: customer DEBIT restoration for affected unsettled amount; no duplicate cash reversal.

## 3. Incoming PROMISSORY_NOTE
Same financial recognition as incoming check.
Lifecycle omits bank-specific fields/actions when not applicable:
REGISTERED_DRAFT → RECEIVED_POSTED → PORTFOLIO → ENDORSED or DELIVERED_TO_BANK/COLLECTION_PENDING where supported → PARTIAL/COLLECTED; UNPAID → optional PROTESTED → RETURNED/RESTORED.
Protest is evidence/legal state, not a second posting.

## 4. Outgoing CHECK
REGISTERED_DRAFT → ISSUED → DELIVERED_POSTED → PENDING_MATURITY → PARTIALLY_PAID → PAID/CLEARED.
Before clearing: RETURNED/CANCELLED may compensate delivery.
UNPAID/BOUNCED may remain outstanding or be returned/cancelled according to evidence.
Delivery POST: SUPPLIER DEBIT + instrument payable increase; Cash/Bank NONE.
Clearing: instrument payable decrease + Bank OUT.

## 5. Outgoing PROMISSORY_NOTE
REGISTERED_DRAFT → ISSUED → DELIVERED_POSTED → PENDING_MATURITY → PARTIALLY_PAID → PAID.
UNPAID may become PROTESTED where applicable.
Return/cancel before payment compensates supplier settlement and closes instrument obligation.
Cash/Bank changes only on actual payment.

## 6. Endorsement
Eligibility:
- INCOMING;
- PORTFOLIO custody;
- posted/open instrument receivable;
- not protested/returned/reversed;
- remaining eligible amount equals endorsed amount;
- same company/currency target Supplier Party.

POST:
- custody → ENDORSED_PARTY;
- instrument receivable closes/transfers for eligible amount;
- SUPPLIER_PAYABLE DEBIT;
- Cash/Bank NONE.

No partial endorsement in core.
Returned endorsement: linked compensation restores SUPPLIER payable and reopens/re-establishes eligible instrument position/custody. Original endorsement remains.

## 7. Bank collection
PORTFOLIO → BANK_CUSTODY is non-monetary.
Confirmed settlement may be partial:
- processed amount <= remaining;
- Finance Bank/Cash IN once;
- instrument receivable reduced same amount;
- fees separate.
Duplicate settlement identity is rejected/idempotent.

## 8. Bounce / unpaid / protest
Before money realization: restore Party claim where the original receipt/delivery had already settled Party balance.
After a previously posted bank settlement is reversed by bank evidence: compensate Bank plus related instrument/Party position deterministically.
PROTESTED adds evidence/status and possible explicit fee; it never repeats restoration already posted.

## 9. Reversal
Draft cancellation may remove unposted draft.
Posted movement cannot be deleted.
Reverse command checks later dependent movements; if eligible, creates linked compensating instrument and Finance effects.
Stale/concurrent transition returns conflict rather than overwriting state.

## 10. Effect matrix
| Action | Account | Instrument position | Cash/Bank | Custody |
|---|---|---|---|---|
| Incoming receipt POST | CUSTOMER CREDIT | receivable + | NONE | portfolio |
| Incoming bank handoff | NONE | NONE | NONE | bank |
| Incoming collection | NONE | receivable - | IN | settled/bank |
| Incoming bounce/return before collection | CUSTOMER DEBIT | receivable - | NONE | returned |
| Outgoing delivery POST | SUPPLIER DEBIT | payable + | NONE | delivered |
| Outgoing clearing | NONE | payable - | OUT | settled |
| Outgoing cancel/return before clearing | SUPPLIER CREDIT | payable - | NONE | returned |
| Endorse incoming to supplier | SUPPLIER DEBIT | incoming position closes/transfers | NONE | endorsed |
