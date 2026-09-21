# Warehouse Full Test Day Backlog

Do not run these tests during PLAN-006 planning.

| ID | Risk | Scenario | Expected invariant |
|---|---|---|---|
| WH-FTD-001 | negative stock | Dispatch POST exceeds AVAILABLE | blocked; no negative authoritative quantity |
| WH-FTD-002 | concurrent outbound | two Dispatch POSTs consume same stock | only eligible quantity posts |
| WH-FTD-003 | pick vs post | pick then Dispatch POST | pick has no STOCK effect; one outbound at POST |
| WH-FTD-004 | pick without reservation | valid order/dispatch, no Reservation | eligible pick allowed within source+physical availability |
| WH-FTD-005 | reservation bound | pick exceeds linked Reservation remainder | blocked until explicit reservation change |
| WH-FTD-006 | FEFO | multiple valid expiry lots | earliest valid expiry recommended |
| WH-FTD-007 | FEFO override | supervisor selects later valid lot | permission+reason+audit; still eligible/unexpired |
| WH-FTD-008 | expired lot | normal pick scans expired lot | hard blocked |
| WH-FTD-009 | wrong barcode | scan Product B for Product A work | hard blocked |
| WH-FTD-010 | wrong lot | lot belongs another Product | hard blocked |
| WH-FTD-011 | serial mismatch | serial wrong Product/location/state | hard blocked |
| WH-FTD-012 | serial duplicate | same serial posted in two positions concurrently | one authoritative position only |
| WH-FTD-013 | put-away | released stock moved receiving→bin | internal move, company quantity unchanged |
| WH-FTD-014 | capacity | target Location exceeds configured capacity | blocked |
| WH-FTD-015 | quarantine | Goods Receipt POST | on-hand QUARANTINE, not AVAILABLE |
| WH-FTD-016 | release | QC/disposition QUARANTINE→AVAILABLE | net quantity unchanged; status lineage recorded |
| WH-FTD-017 | package split | one Dispatch line in multiple packages | packed total <= picked/eligible qty |
| WH-FTD-018 | pre-post cancel | loaded work cancelled before Dispatch POST | no stock reversal because no stock posted |
| WH-FTD-019 | duplicate Dispatch POST | retry same post | one STOCK OUT/idempotent result |
| WH-FTD-020 | transfer issue | ISSUE source AVAILABLE | source OUT + TRANSIT IN; company total unchanged |
| WH-FTD-021 | partial transfer receive | receive part of issued qty | remainder stays TRANSIT |
| WH-FTD-022 | transfer damage | qty arrives damaged | target DAMAGED/HOLD; still company on-hand |
| WH-FTD-023 | transfer shortage | receive less than issued | unresolved qty remains TRANSIT |
| WH-FTD-024 | loss reconciliation | approved transit loss | explicit adjustment removes qty; lineage/audit retained |
| WH-FTD-025 | duplicate receive | retry transfer receive | no duplicate target IN |
| WH-FTD-026 | transfer reverse | reverse before/after partial receive | compensating history restores consistent positions |
| WH-FTD-027 | blind count | first counter opens session | expected qty hidden from counter |
| WH-FTD-028 | intervening movement | stock moves during count | expected reconciliation includes net intervening movement |
| WH-FTD-029 | zero count diff | counted=expected reconciled | no COUNT_ADJUSTMENT |
| WH-FTD-030 | positive count diff | approved positive discrepancy | explicit positive COUNT_ADJUSTMENT; valuation basis required |
| WH-FTD-031 | negative count diff | approved shortage | explicit negative COUNT_ADJUSTMENT; no stock overwrite |
| WH-FTD-032 | count SoD | counter approves own discrepancy | rejected |
| WH-FTD-033 | duplicate count POST | retry approved count posting | one adjustment |
| WH-FTD-034 | stale count | count POST after untracked/stale state | conflict/reconcile; no blind overwrite |
| WH-FTD-035 | warehouse deactivate | on-hand/reservation/transit/open work exists | blocked |
| WH-FTD-036 | location deactivate | on-hand/open work exists | blocked |
| WH-FTD-037 | scrap | approved damaged quantity disposal | explicit STOCK OUT; no Finance posting by Warehouse |
| WH-FTD-038 | duplicate scrap | retry disposal | one physical OUT |
| WH-FTD-039 | offline retry | same client_operation_id sync twice | one logical effect |
| WH-FTD-040 | offline intentional repeat | two intentional scans | distinct operation IDs, both validated |
| WH-FTD-041 | offline stale location | queued action conflicts with current server position | visible conflict; no overwrite |
| WH-FTD-042 | offline serial conflict | serial moved before queued sync | rejected/conflict |
| WH-FTD-043 | cross-company | work references another company Warehouse/Product | blocked |
| WH-FTD-044 | replenishment | bin replenishment | internal move only; company qty unchanged |
| WH-FTD-045 | Purchase Return | supplier physical return | STOCK OUT once; Finance adjustment separate |
| WH-FTD-046 | Goods Receipt double stock | GR then put-away | only GR creates inbound stock; put-away net zero |
| WH-FTD-047 | invoice no stock | Sales/Supplier Invoice after physical docs | no second stock effect |
| WH-FTD-048 | browser/mobile E2E | receipt→release→put-away→pick→pack→Dispatch | UI states match ledger effects |
| WH-FTD-049 | performance | high-volume scan/pick/stock lists | bounded query/paging/scan latency |
| WH-FTD-050 | device reconnect | offline queue reconnect/retry/order conflict | idempotent effects, explicit dependency conflicts |
| WH-FTD-051 | permissions | operator attempts FEFO override/loss/count approval | rejected |
| WH-FTD-052 | reversal lineage | posted physical movement reversed | original retained + compensating link |
| WH-FTD-053 | count valuation | positive adjustment with missing valuation policy | quantity/value posting does not silently invent zero cost |
| WH-FTD-054 | transit report | partially received transfer | unresolved transit accurately visible |
| WH-FTD-055 | lot trace | receipt→put-away→transfer→dispatch | exact lot movement lineage retained |
| WH-FTD-056 | serial trace | receipt→location→dispatch | one serial history/current position deterministically derived |

## Required setup

When implemented:
- PostgreSQL Inventory Ledger + durable idempotency;
- Sales Dispatch/Reservation fixtures;
- Purchasing Goods Receipt fixtures;
- Product/UOM/lot/serial data;
- Warehouse/Location masters;
- Quality disposition fixtures;
- Finance valuation resolver for value-sensitive count tests;
- mobile/offline device harness;
- concurrency workers.

## Evidence

Record:
- test ID/name;
- tested commit/HEAD;
- environment/setup;
- observed result;
- PASS/FAIL;
- logs/artifacts;
- defect/blocker.

Mock-only evidence does not prove PostgreSQL concurrency, offline/device or real provider behavior.
