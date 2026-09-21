# Sales Full Test Day Backlog

Do not run these tests during PLAN-002 planning.

| ID | Risk | Scenario | Expected invariant |
|---|---|---|---|
| SALES-FTD-001 | E2E mismatch | Quote → partial Order → manual Reservation → Dispatch → Invoice → Collection | each effect occurs once at frozen recognition point |
| SALES-FTD-002 | oversell | concurrent Reservations | committed reservation never exceeds accepted availability |
| SALES-FTD-003 | over-shipment | concurrent Dispatch posts | net shipped never exceeds effective ordered qty |
| SALES-FTD-004 | duplicate dispatch | retry same Dispatch POST | one logical STOCK OUT |
| SALES-FTD-005 | duplicate invoice | retry same Invoice POST | one receivable and one COGS effect |
| SALES-FTD-006 | double stock | Dispatch-sourced Invoice POST | no second STOCK OUT |
| SALES-FTD-007 | partial shipment | multiple Dispatches | shipped/remaining trace exact source lines |
| SALES-FTD-008 | partial invoice | multiple Invoices | invoiced never exceeds eligible source |
| SALES-FTD-009 | mixed partials | reservation + dispatch + invoice | all derived quantities consistent |
| SALES-FTD-010 | dispatch reversal | reverse posted Dispatch | original retained; compensating inventory effect |
| SALES-FTD-011 | invoice reversal | reverse posted Invoice | receivable and COGS reversed without stock effect |
| SALES-FTD-012 | return after invoice | physical return + credit | physical/financial state separate |
| SALES-FTD-013 | return after collection | credit/refund after collection | history preserved; no duplicate account/cash effect |
| SALES-FTD-014 | settlement model | multiple collections against customer | customer balance changes; no Invoice allocation/open-item state |
| SALES-FTD-015 | direct invoice | source-less Invoice POST | receivable + eligible COGS; STOCK = NONE |
| SALES-FTD-016 | snapshot | mutate masters/rates after POST | posted commercial/tax/FX snapshot unchanged |
| SALES-FTD-017 | approval bypass | creator/unauthorized actor attempts approve | server rejects creator==approver and missing permission |
| SALES-FTD-018 | company isolation | cross-company access/post | rejected |
| SALES-FTD-019 | warehouse scope | unauthorized Reservation/Dispatch | rejected; no inventory effect |
| SALES-FTD-020 | stale edit | concurrent Order amendment/processing | deterministic conflict; no silent overwrite |
| SALES-FTD-021 | controlled delta | decrease below processed floor or excess Reservation | blocked until valid floor/release conditions satisfied |
| SALES-FTD-022 | quote revision | revise reviewed Quote | prior revision immutable; exact revision source retained |
| SALES-FTD-023 | quote conversion | repeated partial conversions | cumulative converted <= offered; all-zero remaining → CONVERTED |
| SALES-FTD-024 | provider failure | e-document fail after local POST | Invoice stays POSTED; no repost |
| SALES-FTD-025 | carrier retry | carrier timeout/retry | no duplicate STOCK effect |
| SALES-FTD-026 | browser/API E2E | Sales personas | UI state/actions match domain truth |
| SALES-FTD-027 | performance | high-volume lists/lines | bounded paging/query behavior |
| SALES-FTD-028 | accounting/cost | Dispatch → Invoice → reverse | physical STOCK at Dispatch; COGS at Invoice |
| SALES-FTD-029 | tax/rounding/FX | discounts, KDV, midpoint, FX fallback/override | exact B006 sequence, minor-unit totals and immutable snapshot |
| SALES-FTD-030 | permissions/SoD | policy-compliant vs exception docs | only exception docs require approval; creator cannot approve |
| SALES-FTD-031 | amendment history | change commercial terms after processed scope | old processed scope unchanged; future scope uses amendment/new line |

## Required setup categories

When eventually implemented, scenarios use appropriate:
- PostgreSQL integration/concurrency harness;
- seeded customer/product/order data;
- authoritative Inventory/Finance ledgers;
- role/permission matrix;
- provider sandbox/fault injection where relevant;
- representative performance volume.

## Evidence requirements

For each executed case record:
- exact ID/test name;
- tested commit/HEAD;
- environment/setup;
- observed result;
- PASS/FAIL;
- logs/artifacts;
- defect/blocker if failed.

Mock-only evidence is not provider/production verification.
