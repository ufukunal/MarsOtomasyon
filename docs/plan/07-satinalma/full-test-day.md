# Purchasing Full Test Day Backlog

Do not run these tests during PLAN-005 planning.

| ID | Risk | Scenario | Expected invariant |
|---|---|---|---|
| PUR-FTD-001 | duplicate receipt | retry same Goods Receipt POST | one physical STOCK IN |
| PUR-FTD-002 | concurrent receipt | two receipts consume same PO remainder | cumulative receipt never exceeds approved+tolerance cap |
| PUR-FTD-003 | over-receipt | no tolerance policy | blocked |
| PUR-FTD-004 | tolerance approval | within configured over-receipt max | exception approval required; exact evidence retained |
| PUR-FTD-005 | beyond tolerance | receipt exceeds configured max | blocked |
| PUR-FTD-006 | quarantine | stockable Goods Receipt POST | stock enters QUARANTINE, not AVAILABLE |
| PUR-FTD-007 | QC release | valid disposition release | quantity moves QUARANTINE → AVAILABLE through ledger/status effect |
| PUR-FTD-008 | lot tracking | LOT Product receipt without lot | blocked |
| PUR-FTD-009 | serial tracking | duplicate/missing serial | blocked; one serial instance |
| PUR-FTD-010 | partial receipt | multiple GRs against PO | received/remaining exact |
| PUR-FTD-011 | short close | cancel unreceived remainder | cancelled qty cannot later be received |
| PUR-FTD-012 | duplicate invoice | retry same Supplier Invoice POST | one payable effect |
| PUR-FTD-013 | stock double-post | receipt then invoice | Invoice STOCK = NONE |
| PUR-FTD-014 | stockable direct invoice | direct invoice for STOCKABLE Product | blocked without posted receipt |
| PUR-FTD-015 | service direct invoice | source-less service invoice | financial-only + approval; STOCK = NONE |
| PUR-FTD-016 | 3-way happy path | PO=Receipt=Invoice | MATCHED then POST |
| PUR-FTD-017 | price variance | PO price differs from Invoice, no tolerance | blocked/exception not auto-posted |
| PUR-FTD-018 | approved variance | configured price tolerance + approval | APPROVED_EXCEPTION then POST |
| PUR-FTD-019 | over-invoice | invoice qty > eligible receipt, no policy | blocked |
| PUR-FTD-020 | partial invoice | multiple invoices against receipts | cumulative invoiced never exceeds eligible source |
| PUR-FTD-021 | stale approval | invoice edited after match approval | approval invalidated/re-evaluated |
| PUR-FTD-022 | invoice reversal | reverse posted Supplier Invoice | payable reversed; stock unchanged |
| PUR-FTD-023 | receipt reversal | reverse posted GR with no dependent conflict | compensating physical movement; history retained |
| PUR-FTD-024 | receipt reversal dependency | receipt already transferred/consumed/invoiced inconsistently | unsafe direct reversal blocked |
| PUR-FTD-025 | physical return | supplier return shipment | STOCK OUT only |
| PUR-FTD-026 | financial return | supplier adjustment | payable correction only |
| PUR-FTD-027 | mixed return | physical + financial return | two effects independently traceable |
| PUR-FTD-028 | payment separation | pay posted Supplier Invoice | Finance payable decreases + CASH/BANK OUT; no stock |
| PUR-FTD-029 | supplier scope | inactive/non-SUPPLIER Party used | blocked |
| PUR-FTD-030 | product scope | inactive/non-PURCHASABLE Product used | blocked |
| PUR-FTD-031 | cross-company | PO/GR/Invoice references another company master | blocked |
| PUR-FTD-032 | UOM snapshot | Product conversion changes after receipt/invoice | historical base quantities unchanged |
| PUR-FTD-033 | FX snapshot | FX master changes after invoice POST | posted FX snapshot unchanged |
| PUR-FTD-034 | rounding | discount/tax/rounding edge cases | central deterministic calculation preserved |
| PUR-FTD-035 | imported invoice duplicate | provider/import retry | one logical invoice/payable |
| PUR-FTD-036 | browser/API E2E | PO → partial GR → QC release → invoice → payment link | UI/API state matches authoritative effects |
| PUR-FTD-037 | performance | high-volume PO/receipt/invoice/match lists | bounded paging/query behavior |
| PUR-FTD-038 | permissions/SoD | creator attempts exception approval | rejected |
| PUR-FTD-039 | service 2-way | service PO + invoice | no stock; PO/Invoice match deterministic |
| PUR-FTD-040 | return after payment | financial adjustment after supplier payment | Finance handles liability/refund without stock duplication |

## Required setup

When eventually implemented:
- PostgreSQL concurrency/idempotency harness;
- seeded Supplier Party and Product/UOM/tracking data;
- Inventory Ledger;
- Finance account/payment ledger;
- Quality disposition fixtures;
- permission/SoD matrix;
- provider/import fault injection when applicable.

## Evidence

Record:
- test ID/name;
- tested HEAD;
- setup/environment;
- observed result;
- PASS/FAIL;
- artifacts/logs;
- defect/blocker.

Mock-only tests do not prove provider or production behavior.
