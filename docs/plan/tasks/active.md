# Active Tasks

## P5 — Sales broad implementation tranche definition
**Status:** SCOPE DEFINITION REQUIRED / NOT STARTED

Predecessor:
- INVENTORY-IMP-001 — COMPLETED
- canonical report: docs/plan/04-urun-stok/inventory-imp-001-implementation.md
- tested commit: edcc24f1200b70aad102fc510ad7bec60c4515e7
- Foundation Build 35998315605 — SUCCESS
- Foundation Test Deploy 35998315582 — SUCCESS
- frontend 20 / 20
- Foundation targeted 74 PASS
- migration count 8
- TEST /inventory 200
- /products 200
- /parties 200
- live/ready 200 / 200
- smoke PASS

Direction:
- P5 order moves from Inventory to Sales
- use frozen PLAN-002 Sales contracts and PLAN-010
- define one broad coherent Sales tranche
- do not split every Sales capability into micro-packages merely for implementation convenience

Before implementation:
- inspect current Sales source
- reconcile Quote / Order / Reservation / Dispatch / Invoice ownership
- consume Inventory Reservation and physical-posting authority without duplicating it
- preserve Finance account-ledger / COGS / settlement ownership
- map entities/constraints/migration/API/UI/permissions/idempotency/concurrency/tests
- assign a Sales implementation package ID only after scope is explicit

Do not invent:
- Sales-owned mutable reservation totals
- Sales-owned physical stock balance
- Invoice stock-out when Dispatch already owns physical movement
- Invoice payment/open-item authority
- Collection settlement inside Sales
- Warehouse operational workflow ownership

Planning:
- master 9 / 30 = 30.0%
- P2 8 / 8 = 100.0%
- separate repository inconsistency: docs/db/acceptance-criteria.md says 10 / 30 = 33.3%

Heavy tests remain Full Test Day only.
