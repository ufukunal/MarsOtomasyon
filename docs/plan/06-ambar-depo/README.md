# Warehouse Operations Module Plan

Status: PLAN-006 COMPLETED / FROZEN — planning only.

## Purpose

Warehouse owns the operational execution of physical inventory already defined by Product/Inventory, Sales and Purchasing contracts.

Canonical flows:

Inbound:
Goods Receipt POST
→ QUARANTINE
→ QC/disposition
→ Release
→ Put-away

Outbound:
Sales Order / optional Reservation
→ Dispatch work
→ Pick
→ Pack
→ Stage / Load
→ Dispatch POST
→ physical STOCK OUT

Internal:
Warehouse Transfer
→ ISSUE
→ TRANSIT
→ partial/full RECEIVE
→ reconcile exception

Control:
Stock Count
→ snapshot
→ count/recount
→ review/approval
→ COUNT_ADJUSTMENT

## Authority

- Inventory Ledger is authoritative physical quantity truth.
- Reservation is authoritative non-physical commitment and never a physical stock status.
- Warehouse operations own execution records/work state, but no screen or mutable stock field may replace the ledger.
- Sales Dispatch POST remains the only Sales outbound physical STOCK OUT point.
- Purchasing Goods Receipt POST remains the Purchasing physical STOCK IN point.
- Supplier Invoice and Sales Invoice have no physical stock effect.

## Frozen operating policies

- Negative physical stock is blocked for normal Warehouse commands.
- Pick eligibility requires AVAILABLE disposition, eligible location and valid lot/serial.
- Expiry-tracked stock defaults to FEFO; other stock defaults to FIFO.
- FEFO/FIFO override requires permission + reason + audit and cannot make expired/ineligible stock pickable.
- Pick/pack/stage/load do not post company inventory quantity; Dispatch POST owns Sales STOCK OUT.
- Put-away is an internal location movement and preserves physical quantity/disposition unless a separate disposition action occurs.
- Transfer ISSUE moves source quantity to TRANSIT; RECEIVE moves TRANSIT to target location/status.
- Partial transfer receipt is allowed; unreconciled quantity remains TRANSIT.
- Transfer loss/damage is an explicit exception/adjustment, never silent quantity disappearance.
- Count starts from an authoritative ledger snapshot and tracks intervening movements.
- First count is blind by default; expected quantity becomes visible during review.
- Any non-zero count discrepancy requires review/approval before COUNT_ADJUSTMENT.
- Count never performs direct `stock = counted`.
- Lot/serial/product/location scan mismatch is a hard block.
- Warehouse/Location cannot be finally deactivated while on-hand, Reservation or open warehouse work remains.
- Scrap/disposal posts explicit STOCK OUT from an eligible non-available disposition with reason/approval.
- Offline scans require durable client operation identity; duplicate retry is idempotent, stale conflict is rejected for resolution.

## V38 product reference

Repository V38 Warehouse UX contains:
- Depolar
- Lokasyonlar
- Rezervasyonlar
- Depo Transferleri with Issue / Partial Receive / Receive / Reconcile / Close / Reverse
- Stok Sayımları with Snapshot / Intervening Movements / Review / Approval / Posting
- Lot / Seri
- Karantina / Bloke
- Barkod / Scan Console
- Sales Dispatch tabs for Toplama / Paketler / Kargo / Pre-Shipment QC

V38 also shows Warehouse negative-stock setting as "Engelle". PLAN-006 freezes BLOCK as the project policy rather than allowing silent negative stock.

## Files

- plan.md — frozen operational decisions/effects.
- workflows.md — inbound/outbound/transfer/count/exception workflows.
- forms.md — desktop/mobile warehouse UX.
- data-contract.md — conceptual work/ledger/link contracts.
- permissions.md — posting, override, count and exception permissions.
- integrations.md — scan/device/outbox/idempotency boundaries.
- reports.md — operational KPI/read-model semantics.
- acceptance-criteria.md — completion evidence.
- full-test-day.md — deferred heavy scenarios.

## Out of scope

- physical SQL schema/migrations;
- application/API/TypeScript implementation;
- Finance valuation/cost-layer method;
- Quality module full inspection-plan design;
- carrier/provider implementation;
- replenishment/MRP optimization;
- automated robotics/WMS provider orchestration.
