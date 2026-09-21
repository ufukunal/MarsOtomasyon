# Current Handoff

## Repository

- Repository: ufukunal/MarsOtomasyon
- Allowed branch: main
- Current HEAD: verify from Git at session start
- Master plan: docs/plan/master-project-plan.md
- Session protocol: docs/ai/session-execution-protocol.md

## Current phase

P2 — Core Commercial Workflow Planning

## Planning progress

Counting basis:
- master-project-plan section 8;
- only COMPLETED / FROZEN work packages count.

Current:
- Master planning sequence: 6 / 30 = 20.0%
- P2 core commercial planning: 5 / 8 = 62.5%

Long planning sessions and final SESSION REPORT must include these two progress metrics.

## Completed predecessor

PLAN-006 — Warehouse operational contract

Status: COMPLETED / FROZEN

Primary completion evidence:
- `f0933c9992e16f4336e0f06d55fafbaa44da489f` — PLAN-006 acceptance criteria completed.

Warehouse planning contracts:
- docs/plan/06-ambar-depo/README.md
- docs/plan/06-ambar-depo/plan.md
- docs/plan/06-ambar-depo/workflows.md
- docs/plan/06-ambar-depo/forms.md
- docs/plan/06-ambar-depo/data-contract.md
- docs/plan/06-ambar-depo/permissions.md
- docs/plan/06-ambar-depo/integrations.md
- docs/plan/06-ambar-depo/reports.md
- docs/plan/06-ambar-depo/acceptance-criteria.md
- docs/plan/06-ambar-depo/full-test-day.md

No SQL schema/migration, application code, deployment or heavy tests were created/run by PLAN-006.

## Frozen Warehouse decisions

- normal Warehouse operations cannot create negative authoritative physical stock.
- only AVAILABLE stock is normal pick eligible.
- expiry-tracked stock uses FEFO; other stock uses FIFO.
- FEFO/FIFO override requires permission, reason and still-eligible stock.
- pick/pack/stage/load are operational work states and do not post Sales STOCK OUT.
- Sales Dispatch POST remains the single authoritative Sales STOCK OUT point.
- Reservation remains non-physical; Dispatch POST consumes/releases accepted related Reservation.
- Goods Receipt POST remains the Purchasing inbound STOCK IN point and enters QUARANTINE.
- put-away/replenishment are internal location movements and do not duplicate receipt stock.
- transfer ISSUE moves source AVAILABLE → TRANSIT; RECEIVE moves TRANSIT → target.
- partial transfer receive is allowed and unresolved quantity stays TRANSIT.
- damaged receipt remains on-hand in DAMAGED/QUALITY_HOLD; transit shortage/loss requires explicit approved adjustment.
- count starts from ledger snapshot and incorporates intervening movements.
- first count is blind by default.
- any non-zero count discrepancy requires approval/SoD before COUNT_ADJUSTMENT.
- no direct stock = counted quantity behavior.
- lot/serial/barcode mismatch is a hard block.
- Warehouse/Location deactivation is blocked while stock, Reservation, Transit or open work remains.
- scrap/disposal is explicit approved STOCK OUT; valuation/write-off stays Finance-owned.
- offline mutations use durable client operation identity; retry is idempotent, stale conflict is visible and never silently overwrites server truth.

## V38 reference used

Repository HTML directly supported:
- Warehouses and Locations
- Reservations
- Warehouse Transfers with Issue / Partial Receive / Receive / Reconcile / Close / Reverse
- Stock Counts with Snapshot / Intervening Movements / Review / Approval / Posting
- Lot / Serial
- Quarantine / Blocked
- Barcode / Scan Console
- Sales Dispatch picking/package/pre-shipment workflow
- negative-stock display as "Engelle"

No external web research was required for PLAN-006.

## Next safe work package

PLAN-007 — Finance / Treasury workflow contract

Target:
`docs/plan/09-finans-kasa-banka/`

PLAN-007 is READY but content work has not started.

Before work:
- verify real main HEAD;
- read governance/master/state/handoff;
- read frozen Sales, Party, Product/Inventory, Purchasing and Warehouse contracts;
- inspect current Finance planning files;
- route skills;
- use repository V38 HTML as default product reference;
- report master and P2 planning percentages during work;
- produce CONTEXT RECEIPT.

Do not jump to logical SQL schema, application code or Full Test Day.
