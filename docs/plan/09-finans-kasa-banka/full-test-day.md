# Finance / Treasury Full Test Day Backlog

Do not run these tests during PLAN-007 planning.

| ID | Risk | Scenario | Expected invariant |
|---|---|---|---|
| FIN-FTD-001 | duplicate receivable | retry Sales Invoice Finance POST | one CUSTOMER receivable effect |
| FIN-FTD-002 | duplicate payable | retry Supplier Invoice Finance POST | one SUPPLIER payable effect |
| FIN-FTD-003 | duplicate Collection | retry same Collection POST | one Account CREDIT + one Cash/Bank IN |
| FIN-FTD-004 | duplicate Payment | retry same Payment POST | one Supplier DEBIT + one Cash/Bank OUT |
| FIN-FTD-005 | no invoice allocation | Collection posted against customer with invoices | balance changes; no authoritative Invoice allocation/paid state |
| FIN-FTD-006 | supplier no allocation | Payment posted against supplier with invoices | payable balance changes; no authoritative Supplier Invoice allocation |
| FIN-FTD-007 | customer advance | Collection exceeds receivable | excess only through explicit CUSTOMER_ADVANCE path |
| FIN-FTD-008 | supplier advance | Payment exceeds payable | excess only through explicit SUPPLIER_ADVANCE path |
| FIN-FTD-009 | dual-role Party | same Party Customer + Supplier | role balances remain separate |
| FIN-FTD-010 | unauthorized netting | automatic or unapproved role netting attempted | rejected |
| FIN-FTD-011 | approved netting | same Party/company/currency, valid balances | Customer CREDIT + Supplier DEBIT; no Cash/Bank; no invoice allocation |
| FIN-FTD-012 | netting SoD | creator attempts own approval | rejected |
| FIN-FTD-013 | cash negative | Cash OUT exceeds derived balance | blocked |
| FIN-FTD-014 | concurrent cash spend | two commands consume same Cash balance | no negative authoritative cash |
| FIN-FTD-015 | bank no overdraft | Bank OUT would make balance negative without policy | blocked |
| FIN-FTD-016 | bank overdraft | explicit valid account overdraft policy | posting follows configured cap/policy only |
| FIN-FTD-017 | same-currency transfer | Bank→Cash / Bank→Bank / Cash→Bank | source OUT + target IN atomically; net company money unchanged excluding fee |
| FIN-FTD-018 | transfer fee | same-currency transfer with fee | fee separate; source/target principal not silently distorted |
| FIN-FTD-019 | duplicate transfer | retry transfer POST | one paired transfer effect |
| FIN-FTD-020 | FX transfer | source/target different currencies | exact source/target amounts + actual rate/base values snapshotted |
| FIN-FTD-021 | FX override | manual FX rate/amount override | permission + reason + approval + audit |
| FIN-FTD-022 | realized FX Collection | foreign customer position reduced | realized FX uses weighted carrying base; invoice snapshot unchanged |
| FIN-FTD-023 | realized FX Payment | foreign supplier position reduced | realized FX uses weighted carrying base |
| FIN-FTD-024 | zero-crossing currency position | settlement exceeds old-sign position | old position settled + FX realized; excess becomes new opposite-sign advance at current rate |
| FIN-FTD-025 | unrealized revaluation | period-end revaluation with valid policy | nominal currency balance unchanged; separate unrealized adjustment |
| FIN-FTD-026 | missing revaluation policy | rate/policy absent | revaluation POST blocked |
| FIN-FTD-027 | revaluation reversal | reverse prior unrealized adjustment | original retained; compensating history linked |
| FIN-FTD-028 | closed period | normal posting into CLOSED period | blocked |
| FIN-FTD-029 | frozen period | ordinary posting into FROZEN period | blocked |
| FIN-FTD-030 | frozen override | approved override into FROZEN period | exact transaction/reason/approver audited |
| FIN-FTD-031 | closed correction | reverse historical closed-period transaction | correction posts in eligible open period; original date/history unchanged |
| FIN-FTD-032 | period reopen SoD | unauthorized/self-approved reopen | rejected |
| FIN-FTD-033 | statement duplicate ID | same provider transaction ID imported twice | one logical evidence row / duplicate conflict handling |
| FIN-FTD-034 | statement fingerprint | provider ID absent, likely duplicate | flagged for resolution; not silently discarded |
| FIN-FTD-035 | import authority | import valid bank statement | Bank Ledger/book balance unchanged until Finance transaction POST |
| FIN-FTD-036 | reconciliation suggestion | high-confidence candidate exists | suggestion alone has no financial effect |
| FIN-FTD-037 | match existing | statement matched to posted Bank movement | reconciliation link only; ledger amount unchanged |
| FIN-FTD-038 | create from statement | unmatched statement → proposed Collection/Payment/etc | draft/proposed transaction; normal permission/approval/POST still required |
| FIN-FTD-039 | partial reconciliation | statement split across movements | matched sums capped by both sides; remainder visible |
| FIN-FTD-040 | aggregate reconciliation | several statement lines to one Bank movement | explicit matched amounts; no overmatch |
| FIN-FTD-041 | reversed reconciled movement | matched Bank entry later reversed | reconciliation becomes exception/review; not silently redirected |
| FIN-FTD-042 | ignore statement | operator ignores statement line | explicit permission/reason required |
| FIN-FTD-043 | cash count exact | counted equals ledger expected | no CASH_ADJUSTMENT |
| FIN-FTD-044 | cash count discrepancy | non-zero difference | approval + explicit CASH_ADJUSTMENT |
| FIN-FTD-045 | cash count SoD | counter approves own discrepancy | rejected |
| FIN-FTD-046 | Goods Receipt valuation | inbound provisional value | quantity/value pool increases; moving average deterministic |
| FIN-FTD-047 | internal transfer valuation | Warehouse transfer | valuation pool quantity/value unchanged |
| FIN-FTD-048 | Dispatch valuation | Sales Dispatch POST | inventory value decreases at current moving average; cost bridge created; no COGS yet |
| FIN-FTD-049 | Sales Invoice COGS | Invoice after Dispatch | bridge consumed into COGS once; no second inventory-value reduction |
| FIN-FTD-050 | duplicate COGS | retry Sales Invoice POST | one COGS recognition |
| FIN-FTD-051 | Invoice reversal | reverse Sales Invoice | COGS compensated; physical stock not restored |
| FIN-FTD-052 | Dispatch reversal | reverse Dispatch with/without recognized COGS | compensating physical/value/bridge workflow remains consistent |
| FIN-FTD-053 | late supplier cost | Supplier Invoice price delta after receipt | delta split by source lineage across on-hand/bridge/recognized COGS |
| FIN-FTD-054 | duplicate late cost | retry same cost component | no duplicate valuation/COGS adjustment |
| FIN-FTD-055 | concurrent late cost/dispatch | late cost races with outbound | deterministic source-quantity/value split; no lost/double value |
| FIN-FTD-056 | landed cost | allocated freight/customs/etc | deterministic snapshotted driver; quantity unchanged |
| FIN-FTD-057 | negative stock count | approved negative COUNT_ADJUSTMENT | current moving-average carrying value removed + cost/write-off effect |
| FIN-FTD-058 | positive count with average | positive adjustment in valued pool | current moving average used |
| FIN-FTD-059 | positive count no average | no valid pool average | explicit unit valuation + permission + approval; zero cost forbidden |
| FIN-FTD-060 | scrap | approved Warehouse disposal | physical OUT + Finance carrying-value write-off; no Cash/Party effect |
| FIN-FTD-061 | Purchase Return | physical supplier return | current pool value leaves; supplier adjustment remains separate |
| FIN-FTD-062 | Customer Refund | valid customer credit/RMA entitlement | CUSTOMER DEBIT + Cash/Bank OUT capped by eligibility |
| FIN-FTD-063 | Supplier Refund | valid supplier advance | SUPPLIER CREDIT + Cash/Bank IN capped by eligibility |
| FIN-FTD-064 | refund over-cap | requested refund above entitlement | blocked |
| FIN-FTD-065 | aging rebuild | rebuild projection from ledger | same aging result; no invoice paid/open fields created |
| FIN-FTD-066 | aging FIFO projection | multiple due segments + Collections | report consumes oldest eligible segments only in projection |
| FIN-FTD-067 | risk exposure | receivable + unbilled Sales exposure - credits | deterministic Finance exposure/remaining limit |
| FIN-FTD-068 | stale hold | Sales command races Finance hold change | authoritative hold revalidation prevents bypass |
| FIN-FTD-069 | cross-company | Finance transaction references foreign-company Party/account | blocked |
| FIN-FTD-070 | branch scope | unauthorized Cash/Bank branch | blocked |
| FIN-FTD-071 | reversal retry | same financial reversal submitted twice | one compensating reversal |
| FIN-FTD-072 | ledger immutability | attempt edit/delete posted ledger entry | rejected |
| FIN-FTD-073 | projection failure | cache/report mismatch | rebuilt from authoritative ledgers; no balance mutation |
| FIN-FTD-074 | browser/API E2E | Invoice→Collection, Supplier Invoice→Payment, transfer, reconciliation | UI/API effects match ledgers |
| FIN-FTD-075 | performance | high-volume statements/ledger/aging/cost | bounded paging/query behavior |
| FIN-FTD-076 | security | unauthorized reverse/netting/revalue/period override | rejected and audited |

## Required setup

When implemented:
- PostgreSQL Account/Cash/Bank/Inventory Valuation ledgers;
- Sales/Purchasing/Warehouse source fixtures;
- multi-currency Party and Bank/Cash accounts;
- posting periods;
- FX/reference/revaluation policy fixtures;
- statement import evidence fixtures;
- idempotency/concurrency workers;
- Finance permission/SoD matrix.

## Evidence

Record:
- test ID/name;
- tested commit/HEAD;
- environment/setup;
- observed result;
- PASS/FAIL;
- logs/artifacts;
- defect/blocker.

Mock-only evidence does not prove bank/provider behavior, PostgreSQL concurrency or production financial integrity.
