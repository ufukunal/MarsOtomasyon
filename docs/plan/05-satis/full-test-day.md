# Sales Full Test Day Backlog

Do not run these tests during PLAN-002 planning.

Each item is retained for Full Test Day or the relevant later implementation milestone.

| ID | Risk | Scenario | Expected invariant | Required setup |
|---|---|---|---|---|
| SALES-FTD-001 | end-to-end mismatch | Quote → Order → Reservation → Dispatch → Invoice → Collection | every effect occurs once at its defined recognition point; source links remain traceable | implemented Sales/Inventory/Finance modules and seeded customer/product |
| SALES-FTD-002 | oversell | two concurrent reservations compete for same available quantity | committed reservation never exceeds allowed availability policy | PostgreSQL integration + concurrency harness |
| SALES-FTD-003 | over-shipment | two concurrent dispatches post against same remaining order quantity | net posted shipped quantity never exceeds permitted source quantity | order with limited remaining qty |
| SALES-FTD-004 | duplicate dispatch | same dispatch Post request retried concurrently | one logical STOCK OUT only | idempotency + inventory ledger |
| SALES-FTD-005 | duplicate invoice | same invoice Post request retried | one receivable posting only | account ledger + idempotency |
| SALES-FTD-006 | double stock | posted dispatch invoice generated and posted | invoice creates no second stock-out | dispatch-sourced invoice |
| SALES-FTD-007 | partial shipment | order split across multiple dispatches | shipped and remaining quantities equal source-linked posted movements | multi-line order |
| SALES-FTD-008 | partial invoice | eligible quantity split across multiple invoices | invoiced never exceeds source basis; remaining deterministic | order/dispatch with partial invoiceability |
| SALES-FTD-009 | mixed partials | partial reservation + multiple dispatch + multiple invoice | all quantity views remain internally consistent | multi-line mixed fulfilment |
| SALES-FTD-010 | dispatch reversal | reverse posted dispatch with downstream state | original movement retained; compensating movement restores permitted quantity/state without silent delete | posted dispatch; downstream constraints |
| SALES-FTD-011 | invoice reversal | reverse posted invoice | original account posting retained; linked reverse restores receivable effect | posted invoice |
| SALES-FTD-012 | return after invoice | physical return then credit | physical and financial states remain separate and traceable | invoice + dispatch + RMA |
| SALES-FTD-013 | return after collection | return/credit/refund after customer paid | credit/refund/account/cash effects do not duplicate or erase history | collection model after SALES-B001 |
| SALES-FTD-014 | settlement model | partial collection/advance/multi-invoice or balance-only flow | behavior exactly matches owner-selected SALES-B001 policy | selected settlement implementation |
| SALES-FTD-015 | direct invoice | source-less invoice post | physical/financial effects exactly match SALES-B002 and never double-post | selected direct-invoice policy |
| SALES-FTD-016 | snapshot | master customer/product/tax data changed after posting | historical quote/order/invoice output retains frozen required values | posted docs + mutated master |
| SALES-FTD-017 | approval bypass | actor attempts protected approval/post action without permission | server rejects; UI hiding is irrelevant | authorization matrix |
| SALES-FTD-018 | company isolation | user from company A accesses/posts company B Sales doc | access/post rejected server-side | two-company data/users |
| SALES-FTD-019 | warehouse scope | unauthorized warehouse dispatch/reservation | command rejected; no inventory effect | multiple warehouses/permissions |
| SALES-FTD-020 | stale edit | two users edit/confirm same draft/order | deterministic conflict; no silent overwrite | optimistic concurrency implementation |
| SALES-FTD-021 | cancelled remainder | cancel remaining then attempt new dispatch/invoice | cancelled quantity cannot be processed | partially fulfilled order |
| SALES-FTD-022 | quote revision | revise customer-reviewed quote | old revision remains unchanged; conversion points to exact revision | multiple quote revisions |
| SALES-FTD-023 | quote conversion | full/partial repeated conversion | matches owner-selected SALES-B003 and prevents duplicate conversion | selected quote conversion policy |
| SALES-FTD-024 | provider failure | e-document provider fails after local invoice post | invoice remains posted; provider status retryable/visible; account posting not repeated | provider sandbox/fault injection |
| SALES-FTD-025 | carrier retry | carrier/shipment update times out and retries | no duplicate physical posting; external update idempotent | carrier adapter sandbox |
| SALES-FTD-026 | browser/API E2E | core Sales personas execute primary journeys | state/actions/quantities match API/domain truth | deployed test environment + browser automation |
| SALES-FTD-027 | performance | high-volume Sales lists and order lines | acceptable paging/query latency without unbounded DOM/data load | representative volume + performance harness |
| SALES-FTD-028 | accounting/cost | dispatch/invoice/reversal across cost policy | COST/COGS follows selected SALES-B005 exactly | costing/accounting implementation |
| SALES-FTD-029 | tax/rounding/FX | mixed tax/discount/currency edge cases | deterministic results match SALES-B006 policy | financial calculation fixtures |
| SALES-FTD-030 | permissions/SoD | creator/approver/poster combinations | behavior matches SALES-B007 and Security policy | role/permission matrix |

## Evidence requirements when eventually run

For each executed case record:
- exact test name/id
- tested commit/HEAD
- environment
- setup/data
- observed result
- PASS/FAIL
- logs/artifacts reference
- defect/blocker if failed.

Mock-only evidence cannot be reported as provider or production verification.
