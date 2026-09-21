# PLAN-005 Acceptance Criteria

Status: COMPLETED / FROZEN — planning only.

## 1. Purchase Order

- [x] PO is commercial commitment only.
- [x] PO creates no STOCK, payable or CASH/BANK effect.
- [x] Supplier must be same-company active SUPPLIER Party.
- [x] Product/Variant/UOM eligibility follows PLAN-004.
- [x] conditional approval/SoD is defined.
- [x] controlled amendment/remainder behavior is defined.

## 2. Goods Receipt

- [x] stockable Goods Receipt POST is physical STOCK IN.
- [x] Goods Receipt alone creates no supplier payable.
- [x] stockable receipt enters QUARANTINE first.
- [x] AVAILABLE release is separate disposition action.
- [x] Warehouse/Location/UOM/lot/serial/expiry requirements are explicit.
- [x] service/non-stock acceptance has no stock effect.
- [x] partial/short receipt is allowed.
- [x] over-receipt default BLOCK.
- [x] tolerance policy + exception approval behavior is deterministic.
- [x] receipt reversal uses compensating physical history.

## 3. Supplier Invoice

- [x] Supplier Invoice POST creates supplier payable.
- [x] Supplier Invoice STOCK effect is NONE in every source mode.
- [x] receipt + invoice cannot double-post stock.
- [x] STOCKABLE goods require posted Receipt and 3-way match.
- [x] SERVICE/NON-STOCK PO invoice uses 2-way match.
- [x] controlled direct financial-only invoice is SERVICE/NON-STOCK only.
- [x] partial invoicing is supported.
- [x] over-invoice default BLOCK.
- [x] price/quantity variance policy and approval are deterministic.
- [x] posted tax/FX/calculation snapshot is immutable.
- [x] invoice reversal has no stock effect.

## 4. Match / quantities

- [x] 2-way / 3-way / direct match modes are explicit.
- [x] match statuses are explicit.
- [x] ordered/received/invoiced/returned/remaining quantities are separately defined.
- [x] source-target links are line-level and quantity-bearing.
- [x] stale approval/edit behavior is defined.
- [x] future concurrency/idempotency risks are recorded.

## 5. Finance / return boundaries

- [x] Payment is a separate Finance event.
- [x] Purchasing does not own settlement/allocation.
- [x] physical Purchase Return and financial adjustment are separate.
- [x] Product master does not become valuation authority.
- [x] exact valuation method remains Finance/Costing-owned.

## 6. Calculation

- [x] KDV-exclusive calculation convention is deterministic.
- [x] discount/tax sequence is deterministic.
- [x] decimal/minor-unit rounding is deterministic.
- [x] FX source/date/fallback/override behavior is deterministic.
- [x] posted snapshots are immutable.

## 7. UI / permissions / integrations / reports

- [x] PO/GR/Invoice/3-Way Match/Return UI contract exists.
- [x] approval/match exception permissions are explicit.
- [x] Goods Receipt Warehouse scope is explicit.
- [x] outbox/integration boundaries are explicit.
- [x] supplier performance/report sources are separated from authority.
- [x] Full Test Day backlog covers duplicate/concurrency/partial/match/reversal/return/payment risks.

## 8. Fast verification

Before handoff:
- all ten Purchasing planning files exist/non-empty;
- no placeholder remains;
- PO has no stock/payable effect;
- GR stockable POST = STOCK IN to QUARANTINE;
- Supplier Invoice STOCK = NONE;
- Supplier Invoice payable effect is explicit;
- stockable invoice requires Receipt/3-way match;
- default over-receipt/over-invoice = BLOCK;
- Payment is Finance-owned;
- return physical/financial effects separated;
- no SQL/migration/C#/TypeScript added;
- final main HEAD reverified.

## 9. Exit condition

PLAN-005 Purchasing planning is frozen.

Next repository-defined work package:
PLAN-006 — Warehouse operational contract.

PLAN-006 content starts only in a separate task/session after state/handoff update.
