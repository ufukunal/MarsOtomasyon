# PLAN-006 Acceptance Criteria

Status: COMPLETED / FROZEN — planning only.

## 1. Authority / effects

- [x] Inventory Ledger remains authoritative physical quantity truth.
- [x] Reservation remains non-physical.
- [x] Goods Receipt POST remains inbound STOCK IN to QUARANTINE.
- [x] put-away does not duplicate Goods Receipt STOCK IN.
- [x] pick/pack/stage/load do not create Sales STOCK OUT.
- [x] Sales Dispatch POST remains the authoritative Sales STOCK OUT point.
- [x] Sales/Supplier Invoice have no physical stock effect.
- [x] posted movement correction uses reversal/compensation.

## 2. Outbound

- [x] pick eligibility is deterministic.
- [x] normal negative stock is blocked.
- [x] partial picking is defined.
- [x] Reservation/source limits are defined.
- [x] FEFO for expiry stock and FIFO otherwise are frozen.
- [x] strategy override permission/reason behavior is deterministic.
- [x] expired/blocked stock cannot be made eligible by override.
- [x] lot/serial/barcode mismatch is hard block.
- [x] package/packing relation is defined.
- [x] staging/loading/final verification do not double-post stock.

## 3. Inbound / put-away / disposition

- [x] receipt handoff is QUARANTINE-first.
- [x] QC result and inventory disposition are distinct.
- [x] release/status effect is explicit.
- [x] put-away is an internal location move.
- [x] target Location eligibility/capacity behavior is defined.
- [x] damage/rework/hold semantics preserve physical quantity until explicit disposal/return.

## 4. Transfer

- [x] ISSUE = source OUT + TRANSIT IN.
- [x] RECEIVE = TRANSIT OUT + target IN.
- [x] company total is conserved for normal transfer.
- [x] partial receive is supported.
- [x] unresolved remainder stays TRANSIT.
- [x] damaged received quantity is distinct from lost/short transit.
- [x] loss resolution requires explicit approved adjustment.
- [x] transfer reversal preserves history.

## 5. Stock count

- [x] count starts from ledger snapshot.
- [x] first count is blind by default.
- [x] normal intervening movements are tracked.
- [x] expected reconciliation formula is deterministic.
- [x] recount preserves observations.
- [x] non-zero discrepancy requires approval/SoD.
- [x] COUNT_ADJUSTMENT posts only discrepancy delta.
- [x] direct `stock = counted` is forbidden.
- [x] positive adjustment valuation dependency is explicit without inventing Finance method.

## 6. Master lifecycle / disposal

- [x] Warehouse/Location deactivation blockers are deterministic.
- [x] no automatic stock relocation/deletion on deactivation.
- [x] scrap/disposal is explicit physical STOCK OUT.
- [x] SCRAP is not retained as fake on-hand disposition.
- [x] financial valuation/write-off remains Finance-owned.

## 7. Mobile / offline

- [x] client operation identity is distinct from intentional repeated scan.
- [x] duplicate retry is idempotent.
- [x] server revalidates business truth at sync.
- [x] stale/conflicting offline action is visible and not silently overwritten.
- [x] company/Warehouse/lot/serial permissions remain enforced offline/online.

## 8. UI / permissions / reports / integrations

- [x] V38 Warehouse/Location/Transfer/Count/Lot-Serial/Quarantine/Scan concepts are mapped.
- [x] desktop/mobile work contracts exist.
- [x] privileged override/count/loss/scrap permissions are explicit.
- [x] outbox/device/provider boundaries are explicit.
- [x] Warehouse KPI/read projections do not become stock authority.
- [x] Full Test Day backlog covers duplicate/concurrency/transfer/count/offline/trace risks.

## 9. Fast verification

Before handoff:
- all ten Warehouse planning files exist and are non-empty;
- no placeholder remains;
- negative stock normal operations = BLOCK;
- Reservation remains non-physical;
- pick/pack/stage do not post outbound stock;
- Dispatch POST is single Sales outbound STOCK OUT;
- Goods Receipt is single Purchasing inbound STOCK IN;
- transfer transit math is coherent;
- count uses delta adjustment;
- lot/serial mismatch hard blocks;
- no SQL/migration/C#/TypeScript implementation added;
- final main HEAD reverified.

## 10. Exit condition

PLAN-006 Warehouse operational planning is frozen.

Next repository-defined P2 work package:
PLAN-007 — Finance / Treasury workflow contract.

PLAN-007 content begins only in a separate session after state/handoff update.
