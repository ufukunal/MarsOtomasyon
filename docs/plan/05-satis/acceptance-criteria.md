# PLAN-002 Acceptance Criteria

Status: COMPLETED / FROZEN — planning only.

## 1. Contract coverage

- [x] Quote purpose/effect and revision contract.
- [x] Sales Order purpose/effect contract.
- [x] Reservation is non-physical commitment.
- [x] Dispatch POST is physical STOCK OUT.
- [x] Sales Invoice POST creates receivable.
- [x] Sales Invoice POST recognizes financial COGS.
- [x] Dispatch-sourced/direct Invoice cannot second-post stock.
- [x] Ordered/reserved/shipped/invoiced/returned/remaining semantics.
- [x] Partial shipment and invoicing.
- [x] Partial/repeated Quote conversion with line-level traceability.
- [x] Source-target relations and reversal.
- [x] State machines and controlled amendments.
- [x] Posted immutability and snapshots.
- [x] Balance-only Collection as separate Finance event.
- [x] Physical return vs financial credit/refund separation.
- [x] Planning-level permissions/approval/SoD.
- [x] Integration/outbox/idempotency needs.
- [x] Reporting semantics.
- [x] Full Test Day heavy scenarios.
- [x] No SQL/application implementation.

## 2. Owner decisions resolved

- [x] SALES-B001 — balance-only Collection; no Invoice allocation/open-item authority.
- [x] SALES-B002 — direct/source-less Invoice financial-only; STOCK = NONE.
- [x] SALES-B003 — line/quantity partial + repeated Quote conversion; all-zero remaining → CONVERTED.
- [x] SALES-B004 — manual Reservation.
- [x] SALES-B005 — COGS at Sales Invoice POST.
- [x] SALES-B006 — KDV-exclusive; line discount → document discount → taxable base; deterministic minor-unit rounding; TCMB döviz alış default FX with audited override; immutable posted snapshot.
- [x] SALES-B007 — conditional policy-exception approval; creator != approver.
- [x] SALES-B008 — audited controlled-delta confirmed-order amendment.

## 3. Completion checks

- [x] Direct Invoice behavior deterministic.
- [x] Collection behavior deterministic.
- [x] COGS recognition deterministic.
- [x] tax/discount/rounding/FX deterministic.
- [x] Quote conversion deterministic.
- [x] Reservation trigger deterministic.
- [x] confirmed-order amendment deterministic.
- [x] no STOCK double-post path.
- [x] no second authoritative balance/settlement truth.
- [x] partial/source-target/reversal deterministic.
- [x] implementation no longer requires a material Sales owner-policy guess.

## 4. Fast verification contract

Before handoff:
- all ten Sales planning files exist/non-empty;
- effect matrix covers Quote/Order/Reservation/Dispatch/Invoice/Collection/Return/Proforma;
- B001/B002/B006/B007/B008 text is consistent across planning docs;
- Reservation is non-physical;
- Dispatch-sourced/direct Invoice has no STOCK effect;
- controlled amendment cannot rewrite processed history;
- no SQL/migration/C#/TypeScript implementation was added;
- final main HEAD is verified.

## 5. Exit condition

PLAN-002 planning is frozen.
Next planned work package is PLAN-003 — Party / Customer / Supplier model.
PLAN-003 content work starts only in a separate task/session.
