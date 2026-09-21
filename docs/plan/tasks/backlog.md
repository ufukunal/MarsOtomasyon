# Planning Backlog

## PLAN-002-DECISIONS — Resolve Sales owner decision gates
Target: docs/plan/05-satis/

Resolve SALES-B001 through SALES-B008, update all affected Sales contracts, run document consistency checks and only then mark PLAN-002 completed.

## PLAN-003 — Party / Customer / Supplier model
Target: docs/plan/03-cariler/

Define party roles, contacts, addresses, billing/shipping identity, account behavior and historical snapshots.

Blocked until PLAN-002 is frozen.

## PLAN-004 — Product / Inventory master model
Target: docs/plan/04-urun-stok/

Define products, variants, UOM, barcode, categories, technical files, warehouses, locations, stock statuses, lot/serial.

## PLAN-005 — Purchasing workflow contract
Target: docs/plan/07-satinalma/

Define Purchase Order → Goods Receipt → Supplier Invoice → Payment and 3-way match.

## PLAN-006 — Warehouse operational contract
Target: docs/plan/06-ambar-depo/

Define receiving, put-away, reservation, picking, packing, transfer, transit, counts and shipping verification.

## PLAN-007 — Finance / Treasury workflow contract
Target: docs/plan/09-finans-kasa-banka/

Define account, cash, bank ledgers, settlement, transfer, reconciliation and FX behavior.

## PLAN-008 — Logical database model
Target: docs/db/

Begins only after core transactional workflows are sufficiently frozen.

## PLAN-009 — Commerce/B2B/Architect/Marketplace planning
Target: docs/plan/15-e-ticaret-b2b-api/

Provider capabilities must be verified before implementation.

## PLAN-010 — Full Test Day plan consolidation
Collect heavy scenarios from all modules. Do not execute until explicitly requested.
