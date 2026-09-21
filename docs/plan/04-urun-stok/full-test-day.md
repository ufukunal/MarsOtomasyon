# Product / Inventory Master Full Test Day Backlog

Do not run these tests during PLAN-004 planning.

| ID | Risk | Scenario | Expected invariant | Required setup |
|---|---|---|---|---|
| PROD-FTD-001 | duplicate code | concurrent Product creation with same Product Code | one accepted identity; duplicate conflicts durably | PostgreSQL concurrency |
| PROD-FTD-002 | duplicate SKU | two active Variants use same code in accepted namespace | ambiguity rejected | Product/Variant persistence |
| PROD-FTD-003 | duplicate barcode | same active barcode mapped to two trade identities | ambiguous mapping rejected | barcode constraints |
| PROD-FTD-004 | GS1 mapping | one GTIN assigned to conflicting Product/Variant/package | conflict; one trade-item mapping | barcode/GS1 validation |
| PROD-FTD-005 | dual barcode | one Product has several legitimate barcodes | each resolves deterministically to Product/Variant/UOM | barcode fixtures |
| PROD-FTD-006 | UOM conversion | order/receipt uses alternate UOM | base quantity deterministic and snapshot retained | document + UOM |
| PROD-FTD-007 | UOM edit history | conversion changes after posted movement | old movement/document quantity interpretation unchanged | posted ledger/docs |
| PROD-FTD-008 | stale UOM edit | transaction created while UOM factor changes | deterministic stale/version conflict; no silent reinterpretation | concurrency |
| PROD-FTD-009 | service stock | SERVICE configured STOCKABLE | rejected | master validation |
| PROD-FTD-010 | inactive Product | inactive Product selected for new Quote/PO | new normal selection rejected; history readable | Sales/Purchasing integration |
| PROD-FTD-011 | inactive with stock | Product deactivated with on-hand | stock remains ledger-derived; no automatic zero/delete | Inventory ledger |
| PROD-FTD-012 | stock truth | attempt to edit Product current stock | no mutable Product stock authority exists | API/domain |
| PROD-FTD-013 | reservation | reserve stock | reserved increases; physical on-hand unchanged | Reservation integration |
| PROD-FTD-014 | availability | available on-hand 10, reservation 4 | available-to-reserve = 6 at same eligible scope | ledger + reservation |
| PROD-FTD-015 | non-available status | quantity moved to QUARANTINE | on-hand retained; available decreases | status movement workflow |
| PROD-FTD-016 | reserved status confusion | reservation created | no physical RESERVED disposition movement | Inventory/Reservation |
| PROD-FTD-017 | warehouse hierarchy | aggregate parent + stock-bearing child | roll-up does not double count child stock | location hierarchy |
| PROD-FTD-018 | inactive location | location with quantity deactivated | history/quantity retained; unsafe new placement blocked | Warehouse master |
| PROD-FTD-019 | cross-company | company A references company B Product/Warehouse | rejected | two-company data |
| PROD-FTD-020 | lot trace | lot receipt → transfer → dispatch | exact lot lineage and net quantity preserved | Inventory integration |
| PROD-FTD-021 | lot expiry | expired lot selected for normal pick | blocked/not normal eligible | lot expiry data |
| PROD-FTD-022 | FEFO | multiple eligible expiry lots | default recommendation earliest expiry first | picking recommendation |
| PROD-FTD-023 | serial duplicate receipt | same Product/serial received twice concurrently | one physical instance; duplicate conflicts | concurrency |
| PROD-FTD-024 | serial two locations | same serial posted into two locations | impossible; one authoritative current position | ledger constraints |
| PROD-FTD-025 | serial fractional | fractional movement for serial instance | rejected | quantity validation |
| PROD-FTD-026 | tracking change | change NONE→SERIAL with existing incompatible stock | blocked until explicit safe transition | existing ledger data |
| PROD-FTD-027 | Sales snapshot | Product code/name/UOM changes after posted Sales doc | historical snapshot unchanged | Sales integration |
| PROD-FTD-028 | Dispatch double-post | Sales Dispatch → Invoice | Dispatch stock-out once; Invoice no stock effect | frozen Sales workflow |
| PROD-FTD-029 | count correction | count discrepancy | adjustment movement, not stock = X rewrite | future Warehouse count |
| PROD-FTD-030 | transfer | source OUT + target IN | company total net unchanged; TRANSIT visible if used | future transfer flow |
| PROD-FTD-031 | valuation boundary | Product reference cost changed | authoritative inventory financial value/history not silently rewritten | Finance integration |
| PROD-FTD-032 | external mapping retry | marketplace product mapping event retried | idempotent mapping; no duplicate Product | provider mapping |
| PROD-FTD-033 | scan mismatch | barcode resolves Product A but scanned lot/serial belongs B | posting blocked | mobile scanning |
| PROD-FTD-034 | browser/API E2E | Product→Variant→UOM→barcode→stock inquiry | UI/API preserve frozen contracts | deployed test env |
| PROD-FTD-035 | performance | high-volume product/barcode/stock lookup | bounded paging/query behavior | representative dataset |
| PROD-FTD-036 | offline scan | duplicate/offline scan sync conflict | no duplicate physical movement; conflict visible | mobile/offline harness |

## Evidence requirements

When eventually run, record:
- exact test ID/name;
- tested commit/HEAD;
- environment;
- setup/data;
- observed result;
- PASS/FAIL;
- logs/artifacts;
- defect/blocker if failed.

Mock-only evidence does not prove provider, scanner, PostgreSQL-concurrency or production behavior.
