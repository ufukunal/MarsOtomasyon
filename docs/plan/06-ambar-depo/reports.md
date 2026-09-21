# Warehouse Reporting Contract

Status: FROZEN PLAN-006 read-model semantics.

Reports/projections never become physical stock authority.

## 1. Stock Status

Grain:
Product/Variant × Warehouse × Location × disposition × optional Lot/Serial.

Measures:
- on_hand;
- available_on_hand;
- reserved;
- available_to_reserve;
- quarantine/hold/rework/damaged;
- transit.

Sources:
- Inventory Ledger;
- Reservation.

No Product/Location mutable current-stock field.

## 2. Receiving / Dock-to-Stock

Receipt grain:
Goods Receipt line.

Timestamps:
- receipt POST;
- release/disposition;
- put-away completion.

KPI:
`dock_to_stock_duration = eligible put-away/release completion - Goods Receipt POST time`

For QC-held stock, report separates:
- receiving time;
- QC wait;
- release-to-put-away time.

## 3. Pick Accuracy

Grain:
Pick work line.

Candidate formula:
`pick_accuracy = correct completed pick lines / completed pick lines`

Mismatch/rework definition must be based on recorded scan/work exceptions.

Do not infer accuracy from lack of audit data.

## 4. Pick Productivity

Measures:
- lines completed;
- base quantity;
- work duration;
- operator;
- zone/Warehouse.

"Lines/hour" must state:
- completed line definition;
- working-time denominator;
- exclusions for paused/blocked work.

Exact labor productivity KPI policy can be refined in Reporting phase.

## 5. Dispatch Readiness

Per Dispatch:
- requested;
- picked;
- packed;
- loaded;
- post status;
- blocked reason;
- Reservation status.

These are operational progress quantities, not inventory truth.

Posted shipped quantity comes from Sales Dispatch/Inventory Ledger.

## 6. Transfer Status

Grain:
Transfer line.

Measures:
- requested;
- issued;
- received;
- damaged received;
- resolved loss;
- unresolved transit.

`unresolved_transit = issued - received - resolved_loss`

Never hide unresolved transit after a partial receipt.

## 7. Stock Count Accuracy

Per count line:
- expected start;
- intervening;
- expected reconciliation;
- counted;
- recount;
- discrepancy;
- adjustment.

Inventory accuracy KPI must define formula and scope explicitly.

Candidate operational formula:
`1 - SUM(abs(discrepancy base qty)) / SUM(expected reconciliation base qty)`
is only a Reporting candidate and is not frozen as management KPI where zero/heterogeneous quantity makes it misleading.

Primary PLAN-006 report therefore exposes raw discrepancy and count-line pass rate.

## 8. Lot / Serial Trace

Lot:
- receipt source;
- current derived Warehouse/Location/status;
- expiry;
- quantity;
- movements;
- Dispatch/Return/Transfer lineage.

Serial:
- exact one-instance movement timeline;
- current derived position/status.

## 9. Quarantine / Hold Aging

Grain:
physical quantity bucket/source lot.

Show:
- source document;
- Product;
- quantity;
- status;
- entered-at;
- QC/result;
- disposition remaining;
- age.

Aging does not auto-release stock.

## 10. Damage / Scrap

Show:
- incident/source;
- Product/lot/serial;
- quantity moved to DAMAGED/HOLD;
- scrap/disposal qty;
- reason/approval;
- financial value only from authorized Finance projection.

## 11. Offline / Scan Conflicts

Operational report:
- device/user;
- operation type;
- queued/synced/conflicted;
- conflict reason;
- age/retries.

No conflict auto-resolution metric may imply an overwritten business state.

## 12. Warehouse/Location utilization

Capacity/occupancy is a projection from accepted location capacity plus current physical quantity/handling unit measures.

Exact cube/weight/pallet utilization requires master dimensions and is not invented if missing.

## 13. Filters/security

All reports enforce:
- company;
- Warehouse access;
- Product/location visibility;
- trace/financial read permission where applicable.

Exports use the same scope.
