# Returns / RMA Full Test Day Backlog

Do not run during PLAN-009 planning.

| ID | Risk | Scenario | Expected invariant |
|---|---|---|---|
| RET-FTD-001 | duplicate case | same logical return accepted twice | durable conflict/idempotent result |
| RET-FTD-002 | duplicate customer receipt | retry receipt POST | one STOCK IN only |
| RET-FTD-003 | duplicate supplier shipment | retry shipment POST | one STOCK OUT only |
| RET-FTD-004 | duplicate credit | retry customer financial credit | one CUSTOMER CREDIT |
| RET-FTD-005 | duplicate adjustment | retry supplier adjustment | one SUPPLIER DEBIT |
| RET-FTD-006 | duplicate refund | retry same refund | one Account + Cash/Bank effect |
| RET-FTD-007 | partial customer return | receive less than authorized | exact physical remainder |
| RET-FTD-008 | partial supplier return | ship less than authorized | exact physical remainder |
| RET-FTD-009 | over-return | source-linked quantity exceeds eligible source | rejected |
| RET-FTD-010 | source-less | approved exception receipt | no fake source; quarantine; valuation controlled |
| RET-FTD-011 | serial duplicate | same serial returned twice | second/concurrent attempt rejected |
| RET-FTD-012 | lot mismatch | wrong source lot | rejected unless valid exception contract |
| RET-FTD-013 | UOM conversion | alternate UOM return | deterministic base quantity/cap |
| RET-FTD-014 | customer receipt | physical receipt only | STOCK IN; no Account/Cash effect |
| RET-FTD-015 | customer credit | financial credit only | CUSTOMER CREDIT; no stock/cash |
| RET-FTD-016 | customer refund | eligible credit refund | CUSTOMER DEBIT + Cash/Bank OUT |
| RET-FTD-017 | supplier shipment | physical return only | STOCK OUT; no Supplier/Cash effect |
| RET-FTD-018 | supplier adjustment | financial adjustment only | SUPPLIER DEBIT; no stock/cash |
| RET-FTD-019 | supplier refund | eligible supplier debit refund | SUPPLIER CREDIT + Cash/Bank IN |
| RET-FTD-020 | QC | quarantine to available/hold/rework/damaged | disposition only; no duplicate quantity |
| RET-FTD-021 | scrap | returned damaged item scrapped | explicit STOCK OUT + Finance write-off |
| RET-FTD-022 | reversal dependency | reverse receipt after move/scrap | blocked until dependency compensation |
| RET-FTD-023 | refund dependency | reverse credit after refund | blocked/compensation chain |
| RET-FTD-024 | concurrency | two returns consume same source remainder | cumulative cap preserved |
| RET-FTD-025 | concurrency | supplier shipment races other stock movement | no negative/duplicate physical effect |
| RET-FTD-026 | stale state | two actors transition same case | stale command conflicts |
| RET-FTD-027 | cross-company | foreign source/Party/warehouse/account | rejected |
| RET-FTD-028 | SoD | initiator self-approves source-less/high-risk | rejected where approval required |
| RET-FTD-029 | replacement | return linked to replacement Sales flow | no hidden return-side dispatch/invoice |
| RET-FTD-030 | purchase valuation | supplier return OUT | current moving-average value leaves pool once |
| RET-FTD-031 | customer valuation | source-linked customer return | Finance correction follows source cost lineage |
| RET-FTD-032 | source-less valuation | no valid cost basis | explicit approved value required |
| RET-FTD-033 | FX | foreign-currency credit/refund | source snapshot immutable; Finance FX deterministic |
| RET-FTD-034 | period | financial post/reversal into frozen/closed period | Finance period gate enforced |
| RET-FTD-035 | projection rebuild | return read model lost | rebuilt from return + Inventory + Finance authorities |
| RET-FTD-036 | bulk | mixed eligible/ineligible return batch | per-item results; no hidden partial failure |
| RET-FTD-037 | security | unauthorized receive/ship/credit/refund/export | rejected and audited |
| RET-FTD-038 | performance | high-volume pending/QC/partial reports | bounded paging/filter behavior |

Future setup: PostgreSQL return/source model, Inventory/Finance ledgers, source Sales/Purchasing fixtures, lot/serial/UOM, permissions/SoD, FX/period and concurrency/idempotency harness.

Evidence must record test ID, commit/HEAD, environment, observed result and PASS/FAIL. Mock evidence does not prove external provider behavior.
