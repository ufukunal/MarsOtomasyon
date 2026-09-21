# Checks / Promissory Notes Full Test Day Backlog

Do not run during PLAN-008 planning.

| ID | Risk | Scenario | Expected invariant |
|---|---|---|---|
| INS-FTD-001 | duplicate identity | same accepted instrument registered twice | one accepted logical instrument / conflict |
| INS-FTD-002 | duplicate receipt | retry incoming receipt POST | one Customer CREDIT + one instrument receivable |
| INS-FTD-003 | duplicate delivery | retry outgoing delivery POST | one Supplier DEBIT + one instrument payable |
| INS-FTD-004 | duplicate collection | retry same bank settlement | one position decrease + one Bank/Cash IN |
| INS-FTD-005 | duplicate payment | retry outgoing clearing | one position decrease + one Bank OUT |
| INS-FTD-006 | bank custody | deliver incoming instrument to bank | no Bank Ledger money effect |
| INS-FTD-007 | partial collection | settle less than remaining | processed increases; exact remainder open |
| INS-FTD-008 | over-process | settlement exceeds remaining | rejected |
| INS-FTD-009 | endorsement | endorse eligible incoming instrument | Supplier DEBIT; no Cash/Bank; custody target updated |
| INS-FTD-010 | partial endorsement | attempt split endorsement | rejected in core |
| INS-FTD-011 | endorsement return | endorsed instrument returned unpaid | supplier obligation restored; original history retained |
| INS-FTD-012 | incoming bounce | received instrument unpaid | Customer claim restored exactly once |
| INS-FTD-013 | protest | protest after unpaid | evidence/fee explicit; no duplicate restoration |
| INS-FTD-014 | outgoing cancel | delivered instrument returned before clearing | Supplier payable restored; no Bank OUT |
| INS-FTD-015 | reversal | reverse eligible posted movement | linked compensation; original retained |
| INS-FTD-016 | dependent reversal | reverse with later dependent movement | blocked or explicit compensation chain |
| INS-FTD-017 | stale state | two actors transition same instrument | stale command conflicts |
| INS-FTD-018 | concurrency | collection races bounce/endorsement | at most one valid transition against same remaining |
| INS-FTD-019 | cross-company | foreign Party/account target | rejected |
| INS-FTD-020 | SoD | initiator self-approves high-risk action | rejected where approval required |
| INS-FTD-021 | FX receipt/settlement | foreign-currency instrument | nominal immutable; realized FX Finance-owned |
| INS-FTD-022 | period control | post into FROZEN/CLOSED period | follows Finance period gate |
| INS-FTD-023 | reconciliation | bank statement matches instrument settlement | link only; no duplicate Bank posting |
| INS-FTD-024 | projection rebuild | portfolio/report cache lost | rebuilt from instrument + Finance authority |
| INS-FTD-025 | performance | high-volume maturity/portfolio | bounded paging/filter behavior |
| INS-FTD-026 | security | unauthorized custody/endorse/reverse/export | rejected and audited |

Required future setup: PostgreSQL instrument model, Finance ledgers/positions, multi-currency fixtures, permissions/SoD, bank evidence and concurrency/idempotency harness.

Evidence must record test ID, commit/HEAD, environment, observed result and PASS/FAIL. Mock evidence does not prove provider/bank behavior.
