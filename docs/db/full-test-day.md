# Logical Database — Full Test Day Backlog

Do not execute during PLAN-010.

| ID | Risk | Scenario | Expected invariant |
|---|---|---|---|
| DB-FTD-001 | company isolation | cross-company FK/source attempt | rejected |
| DB-FTD-002 | Party duplicate | same active tax identity concurrently created | one accepted identity/conflict |
| DB-FTD-003 | Product code | duplicate code/barcode concurrency | scoped uniqueness preserved |
| DB-FTD-004 | serial | same serial posted to two locations concurrently | one authoritative position |
| DB-FTD-005 | reservation | concurrent reservations exceed available | durable prevention |
| DB-FTD-006 | dispatch | two posts consume same order/stock remainder | caps/stock preserved |
| DB-FTD-007 | sales invoice | concurrent invoice source consumption | eligible source cap preserved |
| DB-FTD-008 | receipt | concurrent Goods Receipt over PO tolerance | cap/approval preserved |
| DB-FTD-009 | supplier invoice | concurrent invoice against same receipt remainder | cap preserved |
| DB-FTD-010 | return | concurrent returns consume same source remainder | cap preserved |
| DB-FTD-011 | return serial | same serial returned twice | rejected |
| DB-FTD-012 | account posting | duplicate Collection/Payment/Refund | one Account + money effect |
| DB-FTD-013 | transfer | duplicate treasury/warehouse transfer command | one logical effect |
| DB-FTD-014 | reconciliation | concurrent match over same remainder | no overmatch |
| DB-FTD-015 | instrument | settlement and endorsement race | custody/remaining invariant preserved |
| DB-FTD-016 | reversal | duplicate reversal request | one compensation chain |
| DB-FTD-017 | posting period | financial post into FROZEN/CLOSED | policy enforced |
| DB-FTD-018 | valuation | Dispatch bridge consumed twice | impossible |
| DB-FTD-019 | late cost | on-hand/bridge/COGS allocation concurrency | value conserved/source-linked |
| DB-FTD-020 | projection rebuild | delete/rebuild stock/balance/aging projections | authoritative results reproduced |
| DB-FTD-021 | snapshot | master edit after posting | historical snapshot unchanged |
| DB-FTD-022 | idempotency | Valkey unavailable during retry | PostgreSQL still prevents duplicate |
| DB-FTD-023 | outbox | transaction commit/worker retry | business + outbox atomic, delivery retry safe |
| DB-FTD-024 | backfill | interrupted large projection/backfill | resumable/idempotent |
| DB-FTD-025 | migration | add FK/unique/index to realistic volume | lock/deploy risk within accepted plan |
| DB-FTD-026 | numeric | high precision money/qty/FX boundaries | deterministic decimal behavior |
| DB-FTD-027 | deletion | attempted hard delete of posted source/ledger | blocked/preserved |
| DB-FTD-028 | security | IDOR across company/warehouse/account | rejected |
| DB-FTD-029 | performance | ledger/source work queues at target volume | bounded query plans after physical indexes |
| DB-FTD-030 | restore | backup/restore then projection rebuild | ledger/snapshot invariants retained |

Future evidence must include exact schema/migration commit, PostgreSQL environment/version, test ID, setup, observed result and PASS/FAIL.
