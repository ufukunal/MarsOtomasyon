# PLAN-010 Logical Database Acceptance Criteria

Status: COMPLETED / FROZEN.

- [x] Domain Dictionary expanded to P2-complete vocabulary.
- [x] Bounded-context/module ownership is explicit.
- [x] Logical entity catalog covers Foundation, Party, Product, Inventory/Warehouse, Sales, Purchasing, Finance, Checks/Notes and Returns.
- [x] Commercial documents are not forced into a nullable mega-table.
- [x] Separate Inventory/Account/Cash/Bank/Valuation authorities are preserved.
- [x] Party CUSTOMER/SUPPLIER multi-role model preserved without auto-netting.
- [x] Inventory Ledger and Reservation are separate authorities.
- [x] Location and disposition remain separate.
- [x] Lot/Serial physical lineage and single-position invariant represented.
- [x] Quote→Order→Reservation→Dispatch→Invoice quantity lineage is normalized.
- [x] PO→Receipt→Supplier Invoice lineage/match is normalized.
- [x] Return physical and financial processing remain separate.
- [x] Check/Note custody/lifecycle remains separate from Finance monetary position.
- [x] No Invoice paid/open/open-item allocation authority introduced.
- [x] Finance Transaction and Account/Cash/Bank effect composition is deterministic.
- [x] Moving-average valuation, Dispatch cost bridge and late-cost source lineage are represented.
- [x] Historical Party/Product/UOM/tax/FX/instrument/return snapshots are explicit.
- [x] Rebuildable projections are distinguished from authority.
- [x] Internal/public/business/provider/idempotency identity roles are separated.
- [x] Logical uniqueness, positive-value, source-cap and scope constraints are defined.
- [x] Durable concurrency/idempotency protection areas are defined.
- [x] Outbox/approval/audit/reversal support is defined without duplicate business truth.
- [x] Logical index intent derives from access patterns.
- [x] Migration/backfill/lock conventions are documented.
- [x] PostgreSQL remains authoritative and Valkey non-authoritative.
- [x] No physical SQL, migration, EF model, API/UI code or deployment added.
- [x] Heavy DB/concurrency tests deferred to Full Test Day.
- [x] Active reviewers leave no material logical-model blocker.

Completion metric under current owner-directed PLAN-010 sequence:
- Master completed: 10 / 30 = 33.3%
- P2 remains: 8 / 8 = 100.0%

Note: master-project-plan section 8 labels conceptual item 10 as Quality while current active-task explicitly assigned PLAN-010 to P3 Logical Database Model. Repository task numbering should be normalized before relying on the numeric label alone for the next package.
